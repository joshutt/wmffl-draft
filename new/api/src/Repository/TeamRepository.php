<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of `teamnames` access (the season-scoped display name for a team;
 * distinct from the season-independent `team` table).
 */
class TeamRepository
{
    public function findNameById(int $teamId, int $season): ?string
    {
        $stmt = Db::connection()->prepare(
            'SELECT name FROM teamnames WHERE teamid = ? AND season = ?'
        );
        $stmt->execute([$teamId, $season]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    /**
     * @return array<int, string> teamId => name, for every team in the season
     */
    public function findNamesForSeason(int $season): array
    {
        $stmt = Db::connection()->prepare(
            'SELECT teamid, name FROM teamnames WHERE season = ? ORDER BY name'
        );
        $stmt->execute([$season]);

        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            $names[(int) $row['teamid']] = $row['name'];
        }

        return $names;
    }
}
