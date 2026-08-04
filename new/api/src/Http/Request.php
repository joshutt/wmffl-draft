<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * Parses the request body as JSON. Returns an empty array for an empty
     * or non-object body rather than throwing — callers validate the fields
     * they actually need.
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
