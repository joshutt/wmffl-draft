<?php

declare(strict_types=1);

namespace App\Tests;

use App\Db;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the clock bug fixed in Db.php (2026-08-23): PHP's
 * default timezone must match MySQL's session timezone, or strtotime() on a
 * fetched TIMESTAMP column silently drifts by the zone offset.
 *
 * DraftPickRepository::maxPickTimestamp() fetches `draftpicks.pickTime` (a
 * TIMESTAMP, returned by MySQL in its session timezone — SYSTEM/America's
 * New York here) and parses it with strtotime(). Db::connection() didn't set
 * PHP's timezone, so it defaulted to UTC: every parsed pickTime landed 4
 * hours in the past, DraftClockService::getPreviousPickTime() then always
 * lost to draft.full.start in its max() comparison, and every pick's
 * deduction used elapsed-time-since-draft-start instead of
 * elapsed-time-since-the-last-pick — draining each newly-credited team's
 * clock by an ever-growing amount instead of just their own turn.
 *
 * Deliberately does NOT extend Support\DbTestCase: that class's setUp()
 * deletes every row from draftpicks/config/roster/etc., which is far more
 * than this needs to touch. This test never reads or writes an application
 * table — it uses MySQL's own NOW()/UNIX_TIMESTAMP() as a self-contained,
 * zone-agnostic oracle, so it fails the same way in any timezone, not just
 * America/New_York.
 */
final class DbTimezoneTest extends TestCase
{
    public function testPhpTimezoneMatchesMysqlSessionTimezoneForTimestampParsing(): void
    {
        $pdo = Db::connection(); // must set date_default_timezone_set() before this returns

        $row = $pdo->query('SELECT NOW() AS now_str, UNIX_TIMESTAMP(NOW()) AS now_epoch')->fetch();
        $phpParsedEpoch = strtotime((string) $row['now_str']);

        // A couple seconds of slack for the two NOW()/strtotime() calls
        // landing on either side of a second boundary — not for zone drift,
        // which is a multiple of 1800s (MySQL only allows half-hour-aligned
        // zone offsets) and would blow this well past any real clock skew.
        self::assertEqualsWithDelta(
            (int) $row['now_epoch'],
            $phpParsedEpoch,
            2,
            "PHP's default timezone (" . date_default_timezone_get() . ') does not match '
                . "MySQL's session timezone, so strtotime() on a fetched TIMESTAMP column "
                . '(e.g. draftpicks.pickTime in DraftPickRepository::maxPickTimestamp()) '
                . 'silently drifts by the zone offset — see Db::connection().',
        );
    }
}
