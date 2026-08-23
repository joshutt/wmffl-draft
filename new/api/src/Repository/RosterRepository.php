<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of the `roster` table checks in DraftUtils.php's confirmPlayerAvailable()
 * and the INSERT in makePick().
 */
class RosterRepository
{
    public function isPlayerAvailable(int $playerId): bool
    {
        return !$this->hasActiveRosterRow($playerId, forUpdate: false);
    }

    /**
     * Same check as isPlayerAvailable(), but with SELECT ... FOR UPDATE so it
     * participates in PickService's transaction lock — the InnoDB gap lock on
     * a non-matching row here is what stops a concurrent transaction from
     * inserting a matching one before this transaction commits (see
     * docs/modernization-spec.md §6).
     */
    public function isPlayerAvailableForUpdate(int $playerId): bool
    {
        return !$this->hasActiveRosterRow($playerId, forUpdate: true);
    }

    private function hasActiveRosterRow(int $playerId, bool $forUpdate): bool
    {
        $sql = 'SELECT PlayerID FROM roster WHERE PlayerID = ? AND DateOff IS NULL';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([$playerId]);

        return $stmt->fetchColumn() !== false;
    }

    public function addPick(int $playerId, int $teamId): void
    {
        $stmt = Db::connection()->prepare(
            'INSERT INTO roster (PlayerID, TeamID, DateOn) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$playerId, $teamId]);
    }

    /**
     * A team's active roster with NFL team and bye week — port of
     * rosterHtml.php's query (minus the teamnames join; the caller already
     * has the team name from TeamRepository).
     *
     * @return list<array{pos:?string,name:string,nflTeam:?string,byeWeek:?int}>
     */
    public function findActiveForTeam(int $teamId, int $season): array
    {
        $stmt = Db::connection()->prepare(
            "SELECT p.pos, CONCAT(p.firstname, ' ', p.lastname) AS playername,
                    n.nflteamid, b.week
             FROM players p
             JOIN roster r ON r.playerid = p.playerid AND r.dateoff IS NULL
             LEFT JOIN nflrosters n ON p.playerid = n.playerid AND n.dateoff IS NULL
             LEFT JOIN nflbyes b ON b.season = ? AND n.nflteamid = b.nflteam
             WHERE r.teamid = ?
             ORDER BY p.pos, p.lastname"
        );
        $stmt->execute([$season, $teamId]);

        $players = [];
        foreach ($stmt->fetchAll() as $row) {
            $players[] = [
                'pos' => $row['pos'],
                'name' => trim((string) $row['playername']),
                'nflTeam' => $row['nflteamid'],
                'byeWeek' => $row['week'] !== null ? (int) $row['week'] : null,
            ];
        }

        return $players;
    }

    /**
     * Active roster count per position for a team — feeds the autopick
     * position-need heuristic (commish/autopick.php's posMap query).
     *
     * @return array<string, int> pos => count
     */
    public function countActiveByPosition(int $teamId): array
    {
        $stmt = Db::connection()->prepare(
            'SELECT p.pos, COUNT(*) AS cnt
             FROM players p
             JOIN roster r ON p.playerid = r.PlayerID AND r.DateOff IS NULL
             WHERE r.teamid = ?
             GROUP BY p.pos'
        );
        $stmt->execute([$teamId]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['pos']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Hard-deletes the player's active roster row — port of undopick.php's
     * DELETE (the legacy undo removes the row entirely rather than setting
     * DateOff, so a re-pick inserts a fresh row).
     */
    public function removeActiveByPlayer(int $playerId): void
    {
        $stmt = Db::connection()->prepare(
            'DELETE FROM roster WHERE DateOff IS NULL AND PlayerID = ?'
        );
        $stmt->execute([$playerId]);
    }
}
