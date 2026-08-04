<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in server that mimics the production
 * .htaccess layout (docs/modernization-spec.md §3): the built SPA at the
 * site root, the API under /api/, and SPA-route fallback to index.html.
 *
 * Run from the repo root (build the SPA first — new/web/build.sh):
 *
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t new/web/dist new/dev-server.php
 *
 * Then open http://127.0.0.1:8080 in a browser. For SPA work with hot
 * reload, use `npm run dev` instead (it proxies /api to this server).
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (str_starts_with($path, '/api')) {
    require __DIR__ . '/api/public/index.php';

    return true;
}

if ($path !== '/' && is_file(__DIR__ . '/web/dist' . $path)) {
    return false; // static asset — let the built-in server handle it
}

// SPA fallback: any other path (/, /commish, ...) gets the app shell.
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/web/dist/index.html');

return true;
