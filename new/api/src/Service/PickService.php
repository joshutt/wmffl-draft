<?php

declare(strict_types=1);

namespace App\Service;

use App\Db;
use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Repository\DraftPickHoldRepository;
use App\Repository\DraftPickRepository;
use App\Repository\PlayerRepository;
use App\Repository\RosterRepository;
use Throwable;

/**
 * Port of the pick-submission logic in DraftUtils.php (makePick,
 * confirmPlayerAvailable, saveTeamPick, checkPreselect), with the
 * transactional race-condition fix from docs/modernization-spec.md §6:
 * the read-check + writes now run in a single transaction with
 * `SELECT ... FOR UPDATE` on the open draftpicks row, so two concurrent
 * submissions can no longer both pass the availability check before either
 * write lands.
 */
final class PickService
{
    public function __construct(
        private readonly DraftPickRepository $draftPicks = new DraftPickRepository(),
        private readonly RosterRepository $roster = new RosterRepository(),
        private readonly PlayerRepository $players = new PlayerRepository(),
        private readonly DraftPickHoldRepository $holds = new DraftPickHoldRepository(),
        private readonly DraftClockService $clock = new DraftClockService(),
    ) {
    }

    /**
     * Submits a pick for $teamId, then auto-advances through any consecutive
     * teams that pre-selected while off the clock (checkPreselect()).
     *
     * @throws PlayerNotFoundException
     * @throws PickConflictException
     */
    public function submitPick(int $teamId, int $playerId, int $season): void
    {
        $this->makePick($teamId, $playerId, $season);
        $this->checkPreselect($season);
    }

    /**
     * @throws PlayerNotFoundException
     * @throws PickConflictException
     */
    public function makePick(int $teamId, int $playerId, int $season): void
    {
        $pdo = Db::connection();
        $pdo->beginTransaction();

        try {
            $openPick = $this->draftPicks->findOpenPickForUpdate($season);
            if ($openPick === null) {
                throw new PickConflictException('No open pick for this season — the draft may already be complete.');
            }
            if ($openPick['teamId'] !== $teamId) {
                throw new PickConflictException('Team is not on the clock.');
            }

            $player = $this->players->findById($playerId);
            if ($player === null) {
                throw new PlayerNotFoundException("The playerId {$playerId} does not exist");
            }
            if (!$this->roster->isPlayerAvailableForUpdate($playerId)) {
                throw new PickConflictException("{$player['firstName']} {$player['lastName']} is already on a team");
            }

            $round = $openPick['round'];
            $pick = $openPick['pick'];
            $totalTimeUsed = $this->clock->getTotalTimeUsed($season, $round, $pick);

            $this->draftPicks->markPicked($season, $round, $pick, $playerId);
            $this->roster->addPick($playerId, $teamId);
            $this->holds->clearForTeamOrPlayer($teamId, $playerId);

            // Must run after markPicked() — it re-queries the (now next) team
            // on the clock, same ordering dependency as the legacy adjustClock().
            $this->clock->adjustClock($season, $teamId, $totalTimeUsed);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function confirmPlayerAvailable(int $playerId): array
    {
        $player = $this->players->findById($playerId);
        if ($player === null) {
            return ["The playerId {$playerId} does not exist"];
        }

        if (!$this->roster->isPlayerAvailable($playerId)) {
            return ["{$player['firstName']} {$player['lastName']} is already on a team"];
        }

        return [];
    }

    public function hold(int $teamId, int $playerId): void
    {
        $this->holds->save($teamId, $playerId);
    }

    public function clearHold(int $teamId): void
    {
        $this->holds->clearForTeam($teamId);
    }

    /**
     * While the team now on the clock has a preselection queued, make that
     * pick and advance — port of DraftUtils.php:checkPreselect().
     *
     * Unlike the legacy version, a held player that turns out to be
     * unavailable (or a stale hold from a team no longer on the clock) stops
     * the loop instead of spinning forever: legacy's checkPreselect() ignores
     * makePick()'s error return, so getPreselection() would keep returning
     * the same un-cleared hold and loop indefinitely — a latent bug this
     * rewrite doesn't reproduce.
     */
    public function checkPreselect(int $season): void
    {
        $teamId = $this->clock->getTeamOnClock($season);

        while ($teamId !== null) {
            $playerId = $this->holds->findPlayerIdForTeam($teamId);
            if ($playerId === null) {
                break;
            }

            try {
                $this->makePick($teamId, $playerId, $season);
            } catch (PickConflictException | PlayerNotFoundException) {
                break;
            }

            $teamId = $this->clock->getTeamOnClock($season);
        }
    }
}
