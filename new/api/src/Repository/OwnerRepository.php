<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of the `owners` join used by the commish presence table
 * (commish/index.php + getLogins.php). Heartbeat/clock config values are
 * deliberately NOT joined here — the legacy query concat()'d `draft.login.*`
 * / `draft.team.*` keys in SQL, which is exactly the ad hoc key-building
 * DraftStateService exists to centralize; CommishService merges them instead.
 */
class OwnerRepository
{
    /**
     * @return list<array{userId:int,userName:string,teamId:int,teamName:string,primary:bool}>
     */
    public function findForSeason(int $season): array
    {
        $stmt = Db::connection()->prepare(
            'SELECT u.UserID, u.Name AS userName, t.teamid, t.name AS teamName, o.`primary`
             FROM owners o
             JOIN user u ON o.userid = u.UserID
             JOIN teamnames t ON o.teamid = t.teamid AND t.season = o.season
             WHERE o.season = ?
             ORDER BY t.name'
        );
        $stmt->execute([$season]);

        $owners = [];
        foreach ($stmt->fetchAll() as $row) {
            $owners[] = [
                'userId' => (int) $row['UserID'],
                'userName' => $row['userName'],
                'teamId' => (int) $row['teamid'],
                'teamName' => $row['teamName'],
                'primary' => (bool) $row['primary'],
            ];
        }

        return $owners;
    }
}
