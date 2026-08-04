<?php

declare(strict_types=1);

// Standalone CLI worker spawned via proc_open by
// PickServiceConcurrencyTest — deliberately a separate OS process (not a
// fork) so it gets its own independent PDO connection, matching how two
// real concurrent HTTP requests would each get their own connection.
// Args: teamId playerId season. Prints exactly one line: OK, CONFLICT:<msg>,
// NOTFOUND:<msg>, or ERROR:<class>:<msg>.

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Exception\PickConflictException;
use App\Exception\PlayerNotFoundException;
use App\Service\PickService;

[, $teamId, $playerId, $season] = $argv;

try {
    (new PickService())->makePick((int) $teamId, (int) $playerId, (int) $season);
    echo "OK\n";
} catch (PickConflictException $e) {
    echo "CONFLICT:{$e->getMessage()}\n";
} catch (PlayerNotFoundException $e) {
    echo "NOTFOUND:{$e->getMessage()}\n";
} catch (Throwable $e) {
    echo 'ERROR:' . get_class($e) . ':' . $e->getMessage() . "\n";
}
