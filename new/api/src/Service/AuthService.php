<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

/**
 * Port of loginA.php's credential check. Session mutation lives in
 * App\Http\Session — this service is only the DB-facing half.
 */
final class AuthService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    /**
     * @return array{teamId:int,userId:int,name:string,commish:bool}|null
     */
    public function attemptLogin(string $username, string $password): ?array
    {
        return $this->users->findActiveByCredentials($username, $password);
    }
}
