<?php

declare(strict_types=1);

use App\Db;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DraftController;
use App\Http\Response;
use App\Http\Router;
use App\Http\Session;

require_once __DIR__ . '/../vendor/autoload.php';

Session::start();

$router = new Router();

// Health check: confirms the front controller is reachable and, if ?db=1 is
// passed, that Db.php can actually open a connection (used to confirm
// staging DB connectivity per docs/modernization-spec.md §9's Aug 4-5 step).
$router->get('/health', function (): void {
    $result = ['status' => 'ok'];

    if (($_GET['db'] ?? '') === '1') {
        try {
            Db::connection()->query('SELECT 1');
            $result['db'] = 'ok';
        } catch (Throwable $e) {
            Response::error('DB connection failed: ' . $e->getMessage(), 500);
        }
    }

    Response::json($result);
});

// Auth / session — replaces loginA.php, logout.php, checkIn.php, stillHere.php
$auth = new AuthController();
$router->post('/auth/login', $auth->login(...));
$router->post('/auth/logout', $auth->logout(...));
$router->get('/session', $auth->session(...));
$router->post('/session/heartbeat', $auth->heartbeat(...));

// Draft board / pick / hold — replaces picks.php, clockService.php,
// setPick.php, saveTeamPick/clearSelection.php
$draft = new DraftController();
$router->get('/draft/board', $draft->board(...));
$router->post('/draft/pick', $draft->pick(...));
$router->post('/draft/hold', $draft->hold(...));
$router->delete('/draft/hold', $draft->clearHold(...));

// /api/players, /api/roster/{teamId}, and all /api/commish/* endpoints land
// in later phases (docs/modernization-spec.md §9: Aug 9-13 / Aug 16-19).

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = preg_replace('#^/api#', '', $path) ?: '/';

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
