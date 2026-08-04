<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Guard;
use App\Http\Response;
use App\Repository\RosterRepository;
use App\Repository\TeamRepository;
use App\Repository\WeekmapRepository;

/**
 * Replaces rosterBlock.php/rosterHtml.php — the per-team roster viewer.
 */
final class RosterController
{
    public function __construct(
        private readonly RosterRepository $roster = new RosterRepository(),
        private readonly TeamRepository $teams = new TeamRepository(),
        private readonly WeekmapRepository $weekmap = new WeekmapRepository(),
    ) {
    }

    /** GET /api/roster/{teamId} */
    public function show(string $teamId): void
    {
        Guard::requireLogin();

        if (!ctype_digit($teamId)) {
            Response::error('teamId must be numeric', 400);
        }
        $teamId = (int) $teamId;

        $season = $this->weekmap->currentSeason();
        $teamName = $this->teams->findNameById($teamId, $season);
        if ($teamName === null) {
            Response::error('Unknown team', 404);
        }

        Response::json([
            'teamId' => $teamId,
            'teamName' => $teamName,
            'players' => $this->roster->findActiveForTeam($teamId, $season),
        ]);
    }
}
