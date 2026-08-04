<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Db;
use App\Tests\Support\DbTestCase;
use App\Tests\Support\SeedsMiniDraft;

/**
 * The test docs/modernization-spec.md §6 exists for: two concurrent
 * submissions for the same pick, each in its own real OS process (own PDO
 * connection, own InnoDB session), racing against the SAME
 * `SELECT ... FOR UPDATE` lock in PickService::makePick(). Before the fix,
 * both could pass the availability check and both writes would land. This
 * cannot be verified by mocking PDO — it depends on real InnoDB row locking.
 */
final class PickServiceConcurrencyTest extends DbTestCase
{
    use SeedsMiniDraft;

    public function testOnlyOneOfTwoConcurrentDuplicateSubmissionsSucceeds(): void
    {
        $this->seedMiniDraft();

        // Two owners' clients both submit team 1's pick of player 100 for
        // the same open slot at (nearly) the same instant.
        $results = $this->runConcurrently([
            [1, 100, $this->season],
            [1, 100, $this->season],
        ]);

        $outcomes = array_map(static fn(string $line) => explode(':', trim($line), 2)[0], $results);
        sort($outcomes);

        self::assertSame(
            ['CONFLICT', 'OK'],
            $outcomes,
            "expected exactly one submission to win and one to be rejected as a conflict, got:\n" . implode("\n", $results)
        );

        $rosterRows = Db::connection()
            ->query('SELECT COUNT(*) FROM roster WHERE PlayerID = 100 AND DateOff IS NULL')
            ->fetchColumn();
        self::assertSame('1', (string) $rosterRows, 'the player must end up on exactly one roster, never zero or two');
    }

    /**
     * Spawns one real PHP CLI process per [teamId, playerId, season] tuple,
     * started back-to-back with no delay so they genuinely contend for the
     * DB lock, then waits for all of them and returns each one's stdout line.
     *
     * @param list<array{0:int,1:int,2:int}> $jobs
     * @return list<string>
     */
    private function runConcurrently(array $jobs): array
    {
        $workerScript = __DIR__ . '/../Support/concurrent_pick_worker.php';
        $processes = [];

        foreach ($jobs as [$teamId, $playerId, $season]) {
            $cmd = ['php', $workerScript, (string) $teamId, (string) $playerId, (string) $season];
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $descriptors, $pipes);
            self::assertIsResource($proc);
            $processes[] = ['proc' => $proc, 'pipes' => $pipes];
        }

        $results = [];
        foreach ($processes as $p) {
            $results[] = stream_get_contents($p['pipes'][1]);
            $stderr = stream_get_contents($p['pipes'][2]);
            fclose($p['pipes'][1]);
            fclose($p['pipes'][2]);
            $exitCode = proc_close($p['proc']);
            self::assertSame(0, $exitCode, "worker process failed (exit {$exitCode}): {$stderr}");
        }

        return $results;
    }
}
