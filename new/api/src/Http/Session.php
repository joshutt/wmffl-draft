<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Thin wrapper around $_SESSION — replaces the legacy pattern of splatting
 * $_SESSION into bare globals (utils/start.php) with typed accessors.
 *
 * Commish gating uses the real `user.commish` column (fetched by
 * UserRepository, stored here at login) rather than the legacy's hardcoded
 * `$usernum == 2` check scattered per-page — docs/modernization-spec.md's
 * parity checklist asks for this to be "done properly via a role check",
 * and the `commish` column already exists and is populated, just unwired.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @param array{teamId:int,userId:int,name:string,commish:bool} $user
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['isin'] = true;
        $_SESSION['teamId'] = $user['teamId'];
        $_SESSION['userId'] = $user['userId'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['commish'] = $user['commish'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return ($_SESSION['isin'] ?? false) === true;
    }

    public static function teamId(): ?int
    {
        return isset($_SESSION['teamId']) ? (int) $_SESSION['teamId'] : null;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : null;
    }

    public static function name(): ?string
    {
        return $_SESSION['name'] ?? null;
    }

    public static function isCommish(): bool
    {
        return ($_SESSION['commish'] ?? false) === true;
    }

    /**
     * @return array{isin:bool,teamId:?int,userId:?int,name:?string,commish:bool}
     */
    public static function current(): array
    {
        return [
            'isin' => self::isLoggedIn(),
            'teamId' => self::teamId(),
            'userId' => self::userId(),
            'name' => self::name(),
            'commish' => self::isCommish(),
        ];
    }
}
