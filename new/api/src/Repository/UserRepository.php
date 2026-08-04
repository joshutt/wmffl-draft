<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of the `user` table lookup in loginA.php. MD5 password comparison is
 * intentionally preserved (docs/modernization-spec.md: "Keep MD5
 * compatibility... Hash algorithm upgrade is explicitly deferred") — the fix
 * here is parameterizing the query, not changing the hash.
 */
class UserRepository
{
    /**
     * @return array{teamId:int,userId:int,name:string,commish:bool}|null
     */
    public function findActiveByCredentials(string $username, string $password): ?array
    {
        $stmt = Db::connection()->prepare(
            "SELECT teamid, name, userid, commish FROM user
             WHERE username = ? AND password = MD5(?) AND active = 'Y'"
        );
        $stmt->execute([$username, $password]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'teamId' => (int) $row['teamid'],
            'userId' => (int) $row['userid'],
            'name' => $row['name'],
            'commish' => (bool) $row['commish'],
        ];
    }
}
