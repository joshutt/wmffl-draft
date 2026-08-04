<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;
use RuntimeException;

/**
 * Minimal port of the `weekmap` lookup in utils/start.php — only the season,
 * since the draft tool has no use for week/weekname (that's broader-league
 * scope, explicitly out of scope per docs/modernization-spec.md §2).
 */
class WeekmapRepository
{
    public function currentSeason(): int
    {
        $stmt = Db::connection()->query(
            'SELECT season FROM weekmap WHERE NOW() BETWEEN startDate AND endDate LIMIT 1'
        );
        $season = $stmt->fetchColumn();

        if ($season === false) {
            throw new RuntimeException('No weekmap row covers the current date — cannot determine season.');
        }

        return (int) $season;
    }
}
