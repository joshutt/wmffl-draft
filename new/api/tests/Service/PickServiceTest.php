<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Db;
use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Service\PickService;
use App\Tests\Support\DbTestCase;
use App\Tests\Support\SeedsMiniDraft;

/**
 * Covers the application-level correctness of PickService::makePick()
 * (docs/modernization-spec.md §6): validation, table writes, and rollback
 * on failure. The actual concurrent-locking guarantee is covered separately
 * in PickServiceConcurrencyTest, since it needs two real OS processes.
 */
final class PickServiceTest extends DbTestCase
{
    use SeedsMiniDraft;

    public function testMakePickUpdatesDraftpicksRosterAndClock(): void
    {
        $this->seedMiniDraft();

        (new PickService())->makePick(1, 100, $this->season);

        $pdo = Db::connection();

        $pick = $pdo->query('SELECT playerid FROM draftpicks WHERE Season = 2026 AND Round = 1 AND Pick = 1')->fetch();
        self::assertSame(100, (int) $pick['playerid']);

        $roster = $pdo->query('SELECT TeamID FROM roster WHERE PlayerID = 100 AND DateOff IS NULL')->fetch();
        self::assertNotFalse($roster, 'expected a roster row for the drafted player');
        self::assertSame(1, (int) $roster['TeamID']);

        // Elapsed time since draft.full.start (pinned to year 2000) vastly
        // exceeds maxTime, so team 1's clock is clamped to 0.
        $team1Clock = $pdo->query("SELECT value FROM config WHERE `key` = 'draft.team.1'")->fetchColumn();
        self::assertSame('0', $team1Clock);

        // Team 2 is now on the clock and gets addTime (30) credited on top
        // of its seeded 100, clamped to maxTime (180) — 100 + 30 = 130.
        $team2Clock = $pdo->query("SELECT value FROM config WHERE `key` = 'draft.team.2'")->fetchColumn();
        self::assertSame('130', $team2Clock);
    }

    public function testMakePickClearsHoldsForTeamAndPlayer(): void
    {
        $this->seedMiniDraft();
        $pdo = Db::connection();
        $pdo->exec('INSERT INTO draftPickHold (teamid, playerid) VALUES (1, 100), (2, 101)');

        (new PickService())->makePick(1, 100, $this->season);

        $remaining = $pdo->query('SELECT teamid FROM draftPickHold')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([2], $remaining, 'only the picking team\'s hold should be cleared (player 101 is untouched)');
    }

    public function testMakePickThrowsWhenPlayerDoesNotExist(): void
    {
        $this->seedMiniDraft();

        $this->expectException(PlayerNotFoundException::class);

        try {
            (new PickService())->makePick(1, 999, $this->season);
        } finally {
            $pick = Db::connection()
                ->query('SELECT playerid FROM draftpicks WHERE Season = 2026 AND Round = 1 AND Pick = 1')
                ->fetch();
            self::assertNull($pick['playerid'], 'failed pick must not have partially written draftpicks');
        }
    }

    public function testMakePickThrowsConflictWhenPlayerAlreadyRostered(): void
    {
        $this->seedMiniDraft();
        Db::connection()->exec('INSERT INTO roster (PlayerID, TeamID, DateOn) VALUES (100, 2, NOW())');

        $this->expectException(PickConflictException::class);
        $this->expectExceptionMessage('already on a team');

        (new PickService())->makePick(1, 100, $this->season);
    }

    public function testMakePickThrowsConflictWhenTeamNotOnClock(): void
    {
        $this->seedMiniDraft();

        $this->expectException(PickConflictException::class);
        $this->expectExceptionMessage('not on the clock');

        // Team 1 is on the clock for pick (1,1); team 2 tries to jump the line.
        (new PickService())->makePick(2, 100, $this->season);
    }

    public function testMakePickThrowsConflictWhenDraftHasNoOpenPick(): void
    {
        $this->seedMiniDraft();
        Db::connection()->exec('UPDATE draftpicks SET playerid = 100 WHERE Round = 1 AND Pick = 1');
        Db::connection()->exec('UPDATE draftpicks SET playerid = 101 WHERE Round = 1 AND Pick = 2');

        $this->expectException(PickConflictException::class);
        $this->expectExceptionMessage('No open pick');

        (new PickService())->makePick(1, 100, $this->season);
    }

    public function testSubmitPickAutoAdvancesThroughPreselections(): void
    {
        $this->seedMiniDraft();
        $pdo = Db::connection();
        // Team 2 (on the clock right after team 1 picks) has preselected 101.
        $pdo->exec('INSERT INTO draftPickHold (teamid, playerid) VALUES (2, 101)');

        (new PickService())->submitPick(1, 100, $this->season);

        $picks = $pdo->query('SELECT Pick, playerid FROM draftpicks ORDER BY Pick')->fetchAll();
        self::assertSame(100, (int) $picks[0]['playerid']);
        self::assertSame(101, (int) $picks[1]['playerid']);

        $holds = $pdo->query('SELECT COUNT(*) FROM draftPickHold')->fetchColumn();
        self::assertSame('0', (string) $holds);
    }

    public function testCheckPreselectStopsInsteadOfLoopingWhenHeldPlayerIsUnavailable(): void
    {
        $this->seedMiniDraft();
        $pdo = Db::connection();
        // Team 2's hold points at a player already rostered elsewhere —
        // legacy's checkPreselect() would loop forever here (see PickService
        // docblock); this rewrite must return instead.
        $pdo->exec('INSERT INTO roster (PlayerID, TeamID, DateOn) VALUES (101, 2, NOW())');
        $pdo->exec('INSERT INTO draftPickHold (teamid, playerid) VALUES (2, 101)');

        (new PickService())->submitPick(1, 100, $this->season);

        $pick2 = $pdo->query('SELECT playerid FROM draftpicks WHERE Pick = 2')->fetchColumn();
        self::assertNull($pick2, 'team 2\'s stale hold must not be force-applied');
    }
}
