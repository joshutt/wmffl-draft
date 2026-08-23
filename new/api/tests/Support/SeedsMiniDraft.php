<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Db;

/**
 * Seeds a minimal 1-round, 2-team draft (season 2026) for PickService tests.
 * draft.full.start and the draftpicks' pickTime are pinned to a fixed date
 * far in the past so DraftClockService's elapsed-time math always produces
 * a total-time-used far larger than maxTime — making the post-pick clock
 * state deterministic (clamped to 0) instead of depending on how fast the
 * test happens to run.
 */
trait SeedsMiniDraft
{
    protected int $season = 2026;

    protected function seedMiniDraft(): void
    {
        $pdo = Db::connection();

        $pdo->exec("
            INSERT INTO teamnames (teamid, season, name, abbrev) VALUES
                (1, {$this->season}, 'Team One', 'T1'),
                (2, {$this->season}, 'Team Two', 'T2')
        ");

        $pdo->exec("
            INSERT INTO draftpicks (Season, Round, Pick, teamid, playerid, pickTime) VALUES
                ({$this->season}, 1, 1, 1, NULL, '2000-01-01 00:00:00'),
                ({$this->season}, 1, 2, 2, NULL, '2000-01-01 00:00:00')
        ");

        $pdo->exec("
            INSERT INTO players (playerid, flmid, lastname, firstname, pos) VALUES
                (100, 9100, 'Smith', 'John', 'RB'),
                (101, 9101, 'Doe', 'Jane', 'WR')
        ");

        $pdo->exec("
            INSERT INTO config (`key`, `value`) VALUES
                ('draft.start', 'true'),
                ('draft.clock.run', 'true'),
                ('draft.clock.maxTime', '180'),
                ('draft.clock.addTime', '30'),
                ('draft.full.start', '946684800'),
                ('draft.team.1', '180'),
                ('draft.team.2', '100')
        ");
    }
}
