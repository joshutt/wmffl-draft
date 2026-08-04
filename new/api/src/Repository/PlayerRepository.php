<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of `newplayers` (+ current `nflrosters` row) access needed by the
 * draft pick flow. Player IDs throughout the new API are always
 * newplayers.playerid — the internal PK, matching what playerList.php's
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
            'SELECT playerid, firstname, lastname, pos FROM newplayers WHERE playerid = ?'
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
             FROM newplayers p
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
