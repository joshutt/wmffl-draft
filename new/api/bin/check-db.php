#!/usr/bin/env php
<?php

declare(strict_types=1);

// Standalone connectivity check — run with `php bin/check-db.php` after
// copying config/db.ini.default to config/db.ini and filling in the
// staging DB credentials (see conf/wmffl.conf for the current values).
// Exits 0 and prints "OK" on success, exits 1 with the error otherwise.

use App\Db;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $pdo = Db::connection();
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    printf("OK — connected, SELECT 1 returned %s\n", $row['ok']);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED — ' . $e->getMessage() . "\n");
    exit(1);
}
