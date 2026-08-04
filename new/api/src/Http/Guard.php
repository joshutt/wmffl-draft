<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Shared authorization checks — the "role check in the API, not scattered
 * per-page" the parity checklist calls for (vs. the legacy ad hoc
 * `$usernum == 2` / `$_SESSION['commish']` checks).
 */
final class Guard
{
    public static function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            Response::error('Not logged in', 401);
        }
    }

    public static function requireCommish(): void
    {
        self::requireLogin();

        if (!Session::isCommish()) {
            Response::error('Commissioner access required', 403);
        }
    }
}
