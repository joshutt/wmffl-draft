<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of `draftPickHold` access — a team's queued preselection made while
 * off the clock. See DraftUtils.php's saveTeamPick()/getPreselection() and
 * clearSelection.php.
 */
class DraftPickHoldRepository
{
    public function save(int $teamId, int $playerId): void
    {
        $stmt = Db::connection()->prepare(
            'REPLACE INTO draftPickHold (teamid, playerid) VALUES (?, ?)'
        );
        $stmt->execute([$teamId, $playerId]);
    }

    public function findPlayerIdForTeam(int $teamId): ?int
    {
        $stmt = Db::connection()->prepare('SELECT playerid FROM draftPickHold WHERE teamid = ?');
        $stmt->execute([$teamId]);
        $playerId = $stmt->fetchColumn();

        return $playerId === false || $playerId === null ? null : (int) $playerId;
    }

    public function clearForTeam(int $teamId): void
    {
        $stmt = Db::connection()->prepare('DELETE FROM draftPickHold WHERE teamid = ?');
        $stmt->execute([$teamId]);
    }

    /**
     * Clears a hold by either side once a pick lands for that team or player
     * — port of makePick()'s `WHERE teamid=$teamid OR playerid=$playerid`.
     */
    public function clearForTeamOrPlayer(int $teamId, int $playerId): void
    {
        $stmt = Db::connection()->prepare(
            'DELETE FROM draftPickHold WHERE teamid = ? OR playerid = ?'
        );
        $stmt->execute([$teamId, $playerId]);
    }
}
