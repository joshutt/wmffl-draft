<?php

declare(strict_types=1);

namespace App\Tests;

use App\Db;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the clock bug fixed twice now in Db.php: once on
 * 2026-08-23 by forcing PHP's default timezone to match MySQL's SYSTEM
 * session timezone, and again on 2026-08-28 when that fix's premise turned
 * out not to hold on staging — identical code, but staging's MySQL SYSTEM
 * zone apparently disagreed with local's, so the two environments computed
 * different epochs for the same stored TIMESTAMP and the clock-reset bug
 * came back.
 *
 * The fix this test now guards: stop relying on PHP's tz matching whatever
 * MySQL's SYSTEM zone happens to be on a given host. Db::connect() instead
 * pins every session to a fixed '+00:00' offset via
 * PDO::MYSQL_ATTR_INIT_COMMAND, and the repositories that read TIMESTAMP
 * columns (DraftPickRepository::maxPickTimestamp() and
 * ::findAllForSeason(), ClockStopRepository::sumExtraSeconds()) use SQL's
 * UNIX_TIMESTAMP() rather than fetching a string and parsing it with PHP's
 * strtotime() — so the epoch no longer depends on PHP's timezone setting at
 * all, on any host.
 *
 * This test asserts the pin itself: MySQL's UNIX_TIMESTAMP(NOW()) must match
 * the real wall-clock epoch (PHP's time(), which is tz-independent — it's
 * always Unix time), catching a regression where either the INIT_COMMAND
 * stops applying (e.g. a connection pool that reuses sessions from outside
 * Db::connect()) or someone reintroduces a named/SYSTEM-zone assumption.
 *
 * Deliberately does NOT extend Support\DbTestCase: that class's setUp()
 * deletes every row from draftpicks/config/roster/etc., which is far more
 * than this needs to touch. This test never reads or writes an application
 * table.
 */
final class DbTimezoneTest extends TestCase
{
    public function testMysqlSessionIsPinnedToUtcRegardlessOfServerOrPhpTimezone(): void
    {
        $pdo = Db::connection();

        $row = $pdo->query("SELECT @@session.time_zone AS tz, UNIX_TIMESTAMP(NOW()) AS now_epoch")->fetch();

        self::assertSame(
            '+00:00',
            $row['tz'],
            'MySQL session timezone is not pinned to +00:00 — Db::connect() should set '
                . "PDO::MYSQL_ATTR_INIT_COMMAND to \"SET time_zone = '+00:00'\" so every "
                . 'session agrees on TIMESTAMP columns regardless of the host\'s SYSTEM zone.',
        );

        // A couple seconds of slack for NOW()/time() landing on either side
        // of a second boundary — not for zone drift, which would be off by
        // a multiple of 1800s (MySQL only allows half-hour-aligned offsets)
        // and would blow this well past any real clock skew.
        self::assertEqualsWithDelta(
            time(),
            (int) $row['now_epoch'],
            2,
            "MySQL's UNIX_TIMESTAMP(NOW()) does not match PHP's time() — the session "
                . "timezone pin in Db::connect() isn't taking effect, so TIMESTAMP columns "
                . '(e.g. draftpicks.pickTime) will be read at the wrong epoch.',
        );
    }
}
