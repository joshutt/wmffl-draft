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
        $stmt = Db::connection()->prepare(
            'SELECT timeStarted, timeStopped FROM draftclockstop
             WHERE season = ? AND round = ? AND pick = ?'
        );
        $stmt->execute([$season, $round, $pick]);

        $totalExtra = 0;
        foreach ($stmt->fetchAll() as $row) {
            $timeStopped = strtotime((string) $row['timeStopped']);
            $timeStarted = $row['timeStarted'] !== null ? strtotime((string) $row['timeStarted']) : time();
            $totalExtra += $timeStarted - $timeStopped;
        }

        return $totalExtra;
    }
}
