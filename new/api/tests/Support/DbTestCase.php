<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Db;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that hit the real local test database (wmffl_test —
 * see tests/schema.sql and config/db.ini). Used only where mocking the
 * repositories wouldn't actually prove anything, e.g. PickService's
 * SELECT ... FOR UPDATE transaction, which depends on real InnoDB locking.
 */
abstract class DbTestCase extends TestCase
{
    private const TABLES = [
        'autodraft_priority', 'draftPickHold', 'draftclockstop', 'roster', 'draftpicks',
        'nflrosters', 'players', 'teamnames', 'config', 'weekmap', 'user',
        'owners', 'playerscores', 'nflbyes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = Db::connection();
        foreach (self::TABLES as $table) {
            $pdo->exec("DELETE FROM `{$table}`");
        }
    }
}
