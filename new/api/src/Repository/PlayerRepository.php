<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of `players` (+ current `nflrosters` row) access needed by the
 * draft pick flow. Player IDs throughout the new API are always
 * players.playerid — the internal PK, matching what playerList.php's
 * "id-<playerid>" values already resolve to in the legacy pick flow.
 */
class PlayerRepository
{
    /**
     * @return array{id:int,firstName:string,lastName:string,pos:?string}|null
     */
    public function findById(int $playerId): ?array
    {
        $stmt = Db::connection()->prepare(
            'SELECT playerid, firstname, lastname, pos FROM players WHERE playerid = ?'
        );
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['playerid'],
            'firstName' => $row['firstname'],
            'lastName' => $row['lastname'],
            'pos' => $row['pos'],
        ];
    }

    /**
     * Undrafted players eligible for the draft, optionally filtered by
     * position and/or current NFL team — port of playerList.php's query
     * (usePos=1, on an active NFL roster, no active WMFFL roster row).
     * Passing null for a filter means "all" (the legacy '*' value).
     *
     * @return list<array{id:int,firstName:?string,lastName:string,pos:?string,nflTeam:string}>
     */
    public function findAvailable(?string $pos, ?string $nfl): array
    {
        $sql = 'SELECT p.playerid, p.lastname, p.firstname, p.pos, r.nflteamid
                FROM players p
                JOIN nflrosters r ON p.playerid = r.playerid AND r.dateoff IS NULL
                LEFT JOIN roster wr ON wr.playerid = p.playerid AND wr.dateoff IS NULL
                WHERE p.usePos = 1 AND wr.teamid IS NULL';
        $params = [];

        if ($pos !== null) {
            $sql .= ' AND p.pos = ?';
            $params[] = $pos;
        }
        if ($nfl !== null) {
            $sql .= ' AND r.nflteamid = ?';
            $params[] = $nfl;
        }

        $sql .= ' ORDER BY p.lastname, p.firstname';

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);

        $players = [];
        foreach ($stmt->fetchAll() as $row) {
            $players[] = [
                'id' => (int) $row['playerid'],
                'firstName' => $row['firstname'],
                'lastName' => $row['lastname'],
                'pos' => $row['pos'],
                'nflTeam' => $row['nflteamid'],
            ];
        }

        return $players;
    }

    /**
     * The best available player by last season's total points (weeks 1-14),
     * optionally restricted to a position set — port of commish/autopick.php's
     * scoring query, RAND() tiebreak included.
     *
     * @param list<string>|null $positions null = any position
     */
    public function findBestAvailableByScore(int $scoreSeason, ?array $positions): ?int
    {
        $sql = "SELECT p.playerid
                FROM players p
                JOIN playerscores ps ON p.playerid = ps.playerid
                LEFT JOIN roster r ON p.playerid = r.playerid AND r.dateoff IS NULL
                LEFT JOIN nflrosters nr ON nr.playerid = p.playerid AND nr.dateoff IS NULL
                WHERE ps.season = ? AND ps.week <= 14 AND r.teamid IS NULL
                  AND p.pos <> 'HC' AND p.usePos = 1 AND p.pos <> ''
                  AND nr.nflteamid IS NOT NULL";
        $params = [$scoreSeason];

        if ($positions !== null && $positions !== []) {
            $placeholders = implode(',', array_fill(0, count($positions), '?'));
            $sql .= " AND p.pos IN ({$placeholders})";
            $params = [...$params, ...$positions];
        }

        $sql .= ' GROUP BY p.playerid ORDER BY SUM(ps.pts) DESC, RAND() LIMIT 1';

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        $playerId = $stmt->fetchColumn();

        return $playerId === false ? null : (int) $playerId;
    }

    /**
     * Player info for a preselection/hold display — mirrors the
     * lastname/firstname/pos/nflteamid concat used in loginA.php and
     * picks.php's preArray, but as structured fields instead of one string.
     *
     * @return array{id:int,firstName:string,lastName:string,pos:?string,nflTeam:?string}|null
     */
    public function findHoldDisplay(int $playerId): ?array
    {
        $stmt = Db::connection()->prepare(
            'SELECT p.playerid, p.firstname, p.lastname, p.pos, r.nflteamid
             FROM players p
             LEFT JOIN nflrosters r ON r.playerid = p.playerid AND r.dateoff IS NULL
             WHERE p.playerid = ?'
        );
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['playerid'],
            'firstName' => $row['firstname'],
            'lastName' => $row['lastname'],
            'pos' => $row['pos'],
            'nflTeam' => $row['nflteamid'],
        ];
    }
}
