<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Service\AuthService;
use App\Service\DraftStateService;

/**
 * Replaces loginA.php / logout.php / checkIn.php / stillHere.php.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly DraftStateService $draftState = new DraftStateService(),
    ) {
    }

    /** POST /api/auth/login */
    public function login(): void
    {
        $body = Request::json();
        $username = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('Please provide username and password', 400);
        }

        $user = $this->auth->attemptLogin($username, $password);
        if ($user === null) {
            Response::error('Invalid username/password combination', 401);
        }

        Session::login($user);
        Response::json(Session::current());
    }

    /** POST /api/auth/logout */
    public function logout(): void
    {
        Session::logout();
        Response::json(['ok' => true]);
    }

    /** GET /api/session — replaces checkIn.php */
    public function session(): void
    {
        Response::json(Session::current());
    }

    /** POST /api/session/heartbeat — replaces stillHere.php */
    public function heartbeat(): void
    {
        $userId = Session::userId();
        if (!Session::isLoggedIn() || $userId === null) {
            Response::error('Not logged in', 401);
        }

        $this->draftState->recordLoginHeartbeat($userId);
        Response::json(['ok' => true]);
    }
}
