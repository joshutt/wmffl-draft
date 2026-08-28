<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Db;
use App\Repository\PriorityListRepository;
use App\Tests\Support\DbTestCase;

/**
 * Import/matching tests against the real test DB (docs/auto-draft-spec.md
 * §11's "Import tests" bullet): a clean row, an unknown name, and an
 * ambiguous name yield one matched row and two pending rows; adding the
 * missing player then running resolvePending() promotes it.
 */
final class PriorityListImportTest extends DbTestCase
{
    public function testCleanUnknownAndAmbiguousRowsSortIntoMatchedAndPending(): void
    {
        $pdo = Db::connection();
        $insertPlayer = $pdo->prepare(
            'INSERT INTO players (playerid, flmid, lastname, firstname, pos, usePos) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $insertPlayer->execute([1, 1, 'Robinson', 'Bijan', 'RB']);
        // Two different players sharing the exact same name — ambiguous.
        $insertPlayer->execute([2, 2, 'Smith', 'Chris', 'RB']);
        $insertPlayer->execute([3, 3, 'Smith', 'Chris', 'RB']);

        $rows = [
            ['pos' => 'RB', 'rank' => 1, 'firstName' => 'Bijan', 'lastName' => 'Robinson'],
            ['pos' => 'RB', 'rank' => 2, 'firstName' => 'Nobody', 'lastName' => 'Unknown'],
            ['pos' => 'RB', 'rank' => 3, 'firstName' => 'Chris', 'lastName' => 'Smith'],
        ];

        $repo = new PriorityListRepository();
        $result = $repo->replace($rows);

        self::assertSame(1, $result['imported']);
        self::assertSame(['RB' => 1], $result['counts']);
        self::assertCount(2, $result['pending']);

        $reasons = array_column($result['pending'], 'reason', 'name');
        self::assertSame('no matching player', $reasons['Nobody Unknown']);
        self::assertSame('matches multiple players', $reasons['Chris Smith']);

        // Add the missing player, then re-resolve — no re-upload needed.
        $insertPlayer->execute([4, 4, 'Unknown', 'Nobody', 'RB']);
        $repo->resolvePending();

        self::assertSame(['RB' => 2], $repo->countsByPosition());
        $stillPending = $repo->pendingRows();
        self::assertCount(1, $stillPending);
        self::assertSame('Chris Smith', $stillPending[0]['name']);
    }

    public function testReplaceOnlyTouchesPositionsPresentInTheUpload(): void
    {
        $pdo = Db::connection();
        $pdo->prepare(
            'INSERT INTO players (playerid, flmid, lastname, firstname, pos, usePos) VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([10, 10, 'Chase', "Ja'Marr", 'WR']);

        $repo = new PriorityListRepository();
        $repo->replace([['pos' => 'WR', 'rank' => 1, 'firstName' => "Ja'Marr", 'lastName' => 'Chase']]);
        self::assertSame(['WR' => 1], $repo->countsByPosition());

        // A second upload for RB only must leave the WR list untouched.
        $repo->replace([['pos' => 'RB', 'rank' => 1, 'firstName' => null, 'lastName' => 'Nobody']]);
        self::assertSame(['WR' => 1], $repo->countsByPosition());
    }
}
