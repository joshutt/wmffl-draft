<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Guard;
use App\Http\Response;
use App\Repository\PlayerRepository;

/**
 * Replaces playerList.php — the available-player list behind the pick form.
 */
final class PlayerController
{
    public function __construct(
        private readonly PlayerRepository $players = new PlayerRepository(),
    ) {
    }

    /** GET /api/players?pos=QB&nfl=GB — omitted/'*' params mean "all" */
    public function list(): void
    {
        Guard::requireLogin();

        $pos = self::filterParam('pos');
        $nfl = self::filterParam('nfl');

        Response::json(['players' => $this->players->findAvailable($pos, $nfl)]);
    }

    /** Legacy clients sent '*' for "no filter"; treat it like an absent param. */
    private static function filterParam(string $name): ?string
    {
        $value = $_GET[$name] ?? '';

        return $value === '' || $value === '*' ? null : (string) $value;
    }
}
