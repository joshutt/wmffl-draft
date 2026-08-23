<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** The submitted player id doesn't exist in players. Maps to HTTP 400. */
final class PlayerNotFoundException extends RuntimeException
{
}
