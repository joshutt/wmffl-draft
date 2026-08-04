<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * The requested pick can't be honored because draft state has moved on
 * underneath it — player already taken, team no longer on the clock, or no
 * open pick left. Maps to HTTP 409 so the SPA can refresh and re-render
 * instead of treating it as a hard failure (docs/modernization-spec.md §6).
 */
final class PickConflictException extends RuntimeException
{
}
