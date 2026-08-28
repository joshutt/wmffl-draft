<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of `draftclockstop` access — manual pauses layered on top of the
 * config-table clock timestamps. See clock.class.php:getExtraTime().
 */
class ClockStopRepository
{
    /**
     * Sum of paused durations for this pick. A row with no timeStarted yet
     * (still paused) is treated as paused until "now" — same as the legacy
     * `$timeStarted = $timeStarted ?? time()` fallback.
     */
    public function sumExtraSeconds(int $season, int $round, int $pick): int
    {
        // UNIX_TIMESTAMP() lets MySQL do the tz conversion in its own
        // session timezone, independent of PHP's date_default_timezone —
        // see DraftPickRepository::maxPickTimestamp() for why fetching the
        // raw string and parsing it with strtotime() is fragile across
        // environments.
        $stmt = Db::connection()->prepare(
            'SELECT UNIX_TIMESTAMP(timeStarted) AS timeStartedEpoch,
                    UNIX_TIMESTAMP(timeStopped) AS timeStoppedEpoch
             FROM draftclockstop
             WHERE season = ? AND round = ? AND pick = ?'
        );
        $stmt->execute([$season, $round, $pick]);

        $totalExtra = 0;
        foreach ($stmt->fetchAll() as $row) {
            $timeStopped = (int) $row['timeStoppedEpoch'];
            $timeStarted = $row['timeStartedEpoch'] !== null ? (int) $row['timeStartedEpoch'] : time();
            $totalExtra += $timeStarted - $timeStopped;
        }

        return $totalExtra;
    }

    /** Records a pause beginning now — stopClock.php's INSERT on stop. */
    public function recordStop(int $season, int $round, int $pick): void
    {
        $stmt = Db::connection()->prepare(
            'INSERT INTO draftclockstop (season, round, pick, timeStopped) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$season, $round, $pick]);
    }

    /** Closes any open pause rows for the pick — stopClock.php's UPDATE on start. */
    public function markResumed(int $season, int $round, int $pick): void
    {
        $stmt = Db::connection()->prepare(
            'UPDATE draftclockstop SET timeStarted = NOW()
             WHERE season = ? AND round = ? AND pick = ? AND timeStarted IS NULL'
        );
        $stmt->execute([$season, $round, $pick]);
    }

    /** Clears the season's pause history — startDraft.php's DELETE. */
    public function deleteForSeason(int $season): void
    {
        $stmt = Db::connection()->prepare('DELETE FROM draftclockstop WHERE season = ?');
        $stmt->execute([$season]);
    }
}
