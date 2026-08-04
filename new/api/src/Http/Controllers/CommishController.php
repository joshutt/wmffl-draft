<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Http\Guard;
use App\Http\Request;
use App\Http\Response;
use App\Repository\PlayerRepository;
use App\Repository\WeekmapRepository;
use App\Service\CommishService;

/**
 * Commissioner console endpoints — replaces commish/index.php's query,
 * getLogins.php, commish/startDraft.php, stopClock.php, commish/autopick.php,
 * and undopick.php. Every route is behind Guard::requireCommish().
 */
final class CommishController
{
    public function __construct(
        private readonly CommishService $commish = new CommishService(),
        private readonly PlayerRepository $players = new PlayerRepository(),
        private readonly WeekmapRepository $weekmap = new WeekmapRepository(),
    ) {
    }

    /** GET /api/commish/status — login/presence table */
    public function status(): void
    {
        Guard::requireCommish();

        Response::json(['owners' => $this->commish->status($this->weekmap->currentSeason())]);
    }

    /** POST /api/commish/draft/start */
    public function startDraft(): void
    {
        Guard::requireCommish();

        $this->commish->startDraft($this->weekmap->currentSeason());
        Response::json(['ok' => true]);
    }

    /** POST /api/commish/clock/start */
    public function startClock(): void
    {
        Guard::requireCommish();

        $this->commish->setClockRunning($this->weekmap->currentSeason(), running: true);
        Response::json(['ok' => true]);
    }

    /** POST /api/commish/clock/stop */
    public function stopClock(): void
    {
        Guard::requireCommish();

        $this->commish->setClockRunning($this->weekmap->currentSeason(), running: false);
        Response::json(['ok' => true]);
    }

    /** POST /api/commish/pick/auto {teamId, pos?} */
    public function autoPick(): void
    {
        Guard::requireCommish();

        $body = Request::json();
        $teamId = $body['teamId'] ?? null;
        if (!is_int($teamId) && !(is_string($teamId) && ctype_digit($teamId))) {
            Response::error('teamId is required', 400);
        }

        $pos = $body['pos'] ?? null;
        if ($pos !== null && (!is_string($pos) || $pos === '' || $pos === '*')) {
            $pos = null;
        }

        try {
            $result = $this->commish->autoPick($this->weekmap->currentSeason(), (int) $teamId, $pos);
        } catch (PlayerNotFoundException $e) {
            Response::error($e->getMessage(), 400);
        } catch (PickConflictException $e) {
            Response::error($e->getMessage(), 409);
        }

        $player = $this->players->findHoldDisplay($result['playerId']);
        Response::json([
            'ok' => true,
            'queued' => $result['queued'],
            'player' => $player === null ? null : [
                'playerId' => $player['id'],
                'name' => "{$player['firstName']} {$player['lastName']}",
                'pos' => $player['pos'],
                'nflTeam' => $player['nflTeam'],
            ],
        ]);
    }

    /** POST /api/commish/pick/undo */
    public function undoPick(): void
    {
        Guard::requireCommish();

        $undone = $this->commish->undoLastPick($this->weekmap->currentSeason());
        if ($undone === null) {
            Response::error('No picks to undo', 409);
        }

        Response::json(['ok' => true, 'undone' => $undone]);
    }
}
