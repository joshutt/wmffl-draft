<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AutoDraftConfig;
use App\Db;
use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Repository\ClockStopRepository;
use App\Repository\DraftPickRepository;
use App\Repository\OwnerRepository;
use App\Repository\PriorityListRepository;
use App\Repository\RosterRepository;
use Throwable;

/**
 * Commissioner operations — ports of commish/startDraft.php, stopClock.php,
 * undopick.php, and commish/autopick.php. Pure orchestration over the same
 * repositories/services the owner-facing flow uses; the legacy scripts'
 * behavior (including their quirks, noted inline) is preserved rather than
 * redesigned, per docs/modernization-spec.md §3.
 */
final class CommishService
{
    /** Minutes since last heartbeat before an owner shows as "Out" (commish/index.php's INTERVAL 2 MINUTE). */
    private const PRESENCE_WINDOW_MINUTES = 2;

    public function __construct(
        private readonly DraftPickRepository $draftPicks = new DraftPickRepository(),
        private readonly RosterRepository $roster = new RosterRepository(),
        private readonly PriorityListRepository $priorityLists = new PriorityListRepository(),
        private readonly ClockStopRepository $clockStops = new ClockStopRepository(),
        private readonly OwnerRepository $owners = new OwnerRepository(),
        private readonly DraftStateService $draftState = new DraftStateService(),
        private readonly DraftClockService $clock = new DraftClockService(),
        private readonly PickService $pickService = new PickService(),
    ) {
    }

    /**
     * The login/presence table — replaces commish/index.php's query and
     * getLogins.php's 5s poll.
     *
     * @return list<array{userId:int,userName:string,teamId:int,teamName:string,
     *     isIn:bool,lastSeen:?string,remainingSeconds:int}>
     */
    public function status(int $season): array
    {
        $heartbeats = $this->draftState->getAllLoginHeartbeats();
        $teamSeconds = $this->draftState->getAllTeamRemainingSeconds();
        $cutoff = time() - self::PRESENCE_WINDOW_MINUTES * 60;

        $rows = [];
        foreach ($this->owners->findForSeason($season) as $owner) {
            $lastSeen = $heartbeats[$owner['userId']] ?? null;
            $rows[] = [
                'userId' => $owner['userId'],
                'userName' => $owner['userName'],
                'teamId' => $owner['teamId'],
                'teamName' => $owner['teamName'],
                'isIn' => $lastSeen !== null && strtotime($lastSeen) > $cutoff,
                'lastSeen' => $lastSeen,
                'remainingSeconds' => $teamSeconds[$owner['teamId']] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Starts the draft — port of commish/startDraft.php: reset every team's
     * clock to the allowed budget, credit the first drafter with the
     * per-pick increment, flip the run/start flags, stamp the start times,
     * clear the season's pause history, then run any queued preselections.
     */
    public function startDraft(int $season): void
    {
        $this->draftState->resetAllTeamClocks($this->draftState->getAllowedSeconds());

        $firstTeam = $this->draftPicks->findTeamForPick($season, round: 1, pick: 1);
        if ($firstTeam !== null) {
            $this->draftState->setTeamRemainingSeconds(
                $firstTeam,
                $this->draftState->getTeamRemainingSeconds($firstTeam) + $this->draftState->getAddTimeSeconds(),
            );
        }

        $this->draftState->setClockRunning(true);
        $this->draftState->setDraftStarted(true);

        $now = time();
        $this->draftState->setClockStartTimestamp($now);
        $this->draftState->setFullStartTimestamp($now);

        $this->clockStops->deleteForSeason($season);

        $this->pickService->checkPreselect($season);
    }

    /**
     * Starts or stops the pick clock — port of stopClock.php. Stopping opens
     * a draftclockstop pause row for the current pick; starting closes it.
     *
     * The round-1-pick-1 block intentionally runs on BOTH start and stop,
     * exactly like the legacy script (its if/else only covers the run flag
     * and the pause row): at the very first pick, either button (re)stamps
     * draft.full.start, flips draft.start on, and credits the first team's
     * increment (clamped to maxTime). Preserved as-is — it's how the draft
     * has always been kicked off when the commish uses the clock buttons
     * instead of Start Draft.
     */
    public function setClockRunning(int $season, bool $running): void
    {
        $current = $this->clock->getCurrentPick($season);
        if ($current === null) {
            return; // no open pick — draft complete, nothing to clock
        }
        $round = $current['round'];
        $pick = $current['pick'];

        $this->draftState->setClockRunning($running);
        if ($running) {
            $this->clockStops->markResumed($season, $round, $pick);
        } else {
            $this->clockStops->recordStop($season, $round, $pick);
        }

        $this->draftState->setClockStartTimestamp(time());

        if ($round === 1 && $pick === 1) {
            $this->draftState->setFullStartTimestamp(time());
            $this->draftState->setDraftStarted(true);

            $maxTime = $this->draftState->getMaxTimeSeconds();
            $credited = $this->draftState->getTeamRemainingSeconds($current['teamId'])
                + $this->draftState->getAddTimeSeconds();
            $this->draftState->setTeamRemainingSeconds($current['teamId'], min($credited, $maxTime));
        }
    }

    /**
     * Undoes the most recent pick — port of undopick.php: reopen the
     * draftpicks row and hard-delete the player's roster row. Like the
     * legacy script, it does NOT re-adjust team clocks or restore holds;
     * the commish re-runs the pick and the clock math self-corrects.
     *
     * @return array{round:int,pick:int,teamId:int,playerId:int}|null the
     *     undone pick, or null if no pick has been made yet
     */
    public function undoLastPick(int $season): ?array
    {
        $pdo = Db::connection();
        $pdo->beginTransaction();

        try {
            $last = $this->draftPicks->findLastFilledPick($season);
            if ($last === null) {
                $pdo->rollBack();

                return null;
            }

            $this->draftPicks->clearPick($season, $last['round'], $last['pick']);
            $this->roster->removeActiveByPlayer($last['playerId']);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return $last;
    }

    /**
     * Force-picks a player for a team — replaces commish/autopick.php's
     * roster-need heuristic with the configuration-driven algorithm in
     * AutoDraftService (docs/auto-draft-spec.md §4/§8). With an explicit
     * $pos (the commish's per-row dropdown) the choice is simply the first
     * available player on that position's priority list, ignoring weights
     * and allocations; otherwise AutoDraftService::selectPlayer() runs the
     * full weighted-position algorithm.
     *
     * Like the legacy flow (autopick.php routing through setPick.php), the
     * pick is made immediately if the team is on the clock, and queued as
     * the team's preselection hold otherwise — this REPLACE INTO silently
     * overwrites any preselection that owner had already queued, which is
     * pre-existing behavior, not new (docs/auto-draft-spec.md §8).
     *
     * @return array{playerId:int,queued:bool}
     * @throws PickConflictException|PlayerNotFoundException
     */
    public function autoPick(int $season, int $teamId, ?string $pos): array
    {
        $maxRound = $this->draftPicks->maxRound($season);
        if ($maxRound === null) {
            throw new PlayerNotFoundException("No draft picks found for season {$season}");
        }

        $autoDraft = new AutoDraftService(
            AutoDraftConfig::load($maxRound),
            $this->roster,
            $this->draftPicks,
            $this->priorityLists,
        );

        $playerId = $pos !== null
            ? $autoDraft->selectPlayerAtPosition($pos)
            : $autoDraft->selectPlayer($season, $teamId);

        if ($this->clock->getTeamOnClock($season) === $teamId) {
            $this->pickService->submitPick($teamId, $playerId, $season);

            return ['playerId' => $playerId, 'queued' => false];
        }

        $this->pickService->hold($teamId, $playerId);

        return ['playerId' => $playerId, 'queued' => true];
    }

    /**
     * Sets the Google Hangout/Meet link shown on the board header — port of
     * commish/updateurl.php. Strips a leading scheme if the commish pastes a
     * full URL, since the value is rendered with `https://` prepended (and
     * legacy stored it bare, e.g. `meet.google.com/abc-defg-hij`).
     */
    public function setHangoutUrl(string $url): void
    {
        $url = trim($url);
        $url = preg_replace('#^https?://#i', '', $url) ?? $url;

        $this->draftState->setHangoutUrl($url);
    }
}
