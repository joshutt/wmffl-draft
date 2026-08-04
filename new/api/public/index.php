<?php

declare(strict_types=1);

use App\Db;
use App\Http\Response;
use App\Http\Router;

require_once __DIR__ . '/../vendor/autoload.php';

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

// Route table for the endpoints in docs/modernization-spec.md §5 is filled in
// as each Service/Repository lands (Aug 6-9+); only /health exists so far.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = preg_replace('#^/api#', '', $path) ?: '/';

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
