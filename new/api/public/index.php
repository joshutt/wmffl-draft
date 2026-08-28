<?php

declare(strict_types=1);

use App\Db;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutoDraftController;
use App\Http\Controllers\CommishController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RosterController;
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

// Player list / roster viewer — replaces playerList.php, rosterHtml.php
$playerController = new PlayerController();
$router->get('/players', $playerController->list(...));
$rosterController = new RosterController();
$router->get('/roster/{teamId}', $rosterController->show(...));

// Commish console — replaces commish/index.php + getLogins.php,
// commish/startDraft.php, stopClock.php, commish/autopick.php, undopick.php
$commish = new CommishController();
$router->get('/commish/status', $commish->status(...));
$router->post('/commish/draft/start', $commish->startDraft(...));
$router->post('/commish/clock/start', $commish->startClock(...));
$router->post('/commish/clock/stop', $commish->stopClock(...));
$router->post('/commish/pick/auto', $commish->autoPick(...));
$router->post('/commish/pick/undo', $commish->undoPick(...));
$router->post('/commish/hangout-url', $commish->setHangoutUrl(...));

// Auto-draft priority lists — replaces nothing legacy, new for
// docs/auto-draft-spec.md §7
$autoDraft = new AutoDraftController();
$router->post('/commish/autodraft/priority', $autoDraft->upload(...));
$router->get('/commish/autodraft/priority', $autoDraft->show(...));

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = preg_replace('#^/api#', '', $path) ?: '/';

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
