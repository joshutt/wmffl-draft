<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repository\DraftPickHoldRepository;
use App\Repository\DraftPickRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use App\Repository\WeekmapRepository;
use App\Service\DraftClockService;
use App\Service\DraftStateService;
use App\Service\PickService;

/**
 * Replaces picks.php + clockService.php (merged into one poll, see
 * docs/modernization-spec.md §5) + setPick.php + saveTeamPick/clearSelection.php.
 */
final class DraftController
{
    public function __construct(
        private readonly DraftPickRepository $draftPicks = new DraftPickRepository(),
        private readonly TeamRepository $teams = new TeamRepository(),
        private readonly DraftPickHoldRepository $holds = new DraftPickHoldRepository(),
        private readonly PlayerRepository $players = new PlayerRepository(),
        private readonly DraftClockService $clock = new DraftClockService(),
        private readonly DraftStateService $draftState = new DraftStateService(),
        private readonly PickService $pickService = new PickService(),
        private readonly WeekmapRepository $weekmap = new WeekmapRepository(),
    ) {
    }

    /** GET /api/draft/board — picks grid + clock + last/on-deck + hold, in one call */
    public function board(): void
    {
        $season = $this->weekmap->currentSeason();
        $allPicks = $this->draftPicks->findAllForSeason($season);

        $openPicks = array_values(array_filter($allPicks, static fn(array $p) => $p['playerId'] === null));
        $filledPicks = array_values(array_filter($allPicks, static fn(array $p) => $p['playerId'] !== null));

        $currentPick = $openPicks[0] ?? null;
        $onDeckPick = $openPicks[1] ?? null;
        $lastPick = $filledPicks !== [] ? $filledPicks[count($filledPicks) - 1] : null;

        $timeRemaining = 0;
        if ($currentPick !== null) {
            $totalUsed = $this->clock->getTotalTimeUsed($season, $currentPick['round'], $currentPick['pick']);
            $avail = $this->clock->getTimeAvail($currentPick['teamId']);
            $timeRemaining = max(0, $avail - $totalUsed);
        }

        $teamNames = $this->teams->findNamesForSeason($season);
        $teamSeconds = $this->draftState->getAllTeamRemainingSeconds();
        $teamClocks = [];
        foreach ($teamNames as $teamId => $name) {
            $teamClocks[] = [
                'teamId' => $teamId,
                'name' => $name,
                'seconds' => $teamSeconds[$teamId] ?? 0,
            ];
        }

        $myHold = null;
        if (Session::isLoggedIn() && Session::teamId() !== null) {
            $heldPlayerId = $this->holds->findPlayerIdForTeam(Session::teamId());
            if ($heldPlayerId !== null) {
                $player = $this->players->findHoldDisplay($heldPlayerId);
                if ($player !== null) {
                    $myHold = [
                        'playerId' => $player['id'],
                        'name' => "{$player['firstName']} {$player['lastName']}",
                        'pos' => $player['pos'],
                        'nflTeam' => $player['nflTeam'],
                    ];
                }
            }
        }

        Response::json([
            'season' => $season,
            'draftStarted' => $this->draftState->isDraftStarted(),
            'clockRunning' => $this->draftState->isClockRunning(),
            'currentPick' => $currentPick,
            'onDeckPick' => $onDeckPick,
            'lastPick' => $lastPick,
            'timeRemaining' => $timeRemaining,
            'picks' => $allPicks,
            'teamClocks' => $teamClocks,
            'myHold' => $myHold,
        ]);
    }

    /** POST /api/draft/pick — replaces setPick.php's on-the-clock branch */
    public function pick(): void
    {
        $teamId = $this->requireTeamId();
        $playerId = self::requirePlayerId();
        $season = $this->weekmap->currentSeason();

        try {
            $this->pickService->submitPick($teamId, $playerId, $season);
        } catch (PlayerNotFoundException $e) {
            Response::error($e->getMessage(), 400);
        } catch (PickConflictException $e) {
            Response::error($e->getMessage(), 409);
        }

        Response::json(['ok' => true]);
    }

    /** POST /api/draft/hold — replaces setPick.php's off-the-clock branch (saveTeamPick) */
    public function hold(): void
    {
        $teamId = $this->requireTeamId();
        $playerId = self::requirePlayerId();

        $this->pickService->hold($teamId, $playerId);
        Response::json(['ok' => true]);
    }

    /** DELETE /api/draft/hold — replaces clearSelection.php */
    public function clearHold(): void
    {
        $teamId = $this->requireTeamId();

        $this->pickService->clearHold($teamId);
        Response::json(['ok' => true]);
    }

    private function requireTeamId(): int
    {
        $teamId = Session::teamId();
        if (!Session::isLoggedIn() || $teamId === null) {
            Response::error('Not logged in', 401);
        }

        return $teamId;
    }

    private static function requirePlayerId(): int
    {
        $body = Request::json();
        $playerId = $body['playerId'] ?? null;

        if (!is_int($playerId) && !(is_string($playerId) && ctype_digit($playerId))) {
            Response::error('playerId is required', 400);
        }

        return (int) $playerId;
    }
}
