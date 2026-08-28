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

    /**
     * Reads an uploaded file from $_FILES — the multipart-upload plumbing
     * this repo didn't have yet (docs/auto-draft-spec.md §7). Validates the
     * PHP upload error code, a size cap, and is_uploaded_file() before
     * handing back the temp path; any failure is reported as "no file"
     * rather than distinguishing the cause, since the caller's only
     * reasonable response is the same 400 either way.
     *
     * @return array{name:string,tmpName:string,size:int}|null
     */
    public static function file(string $field): ?array
    {
        $maxBytes = 5 * 1024 * 1024; // generous for a priority-list CSV

        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] > $maxBytes) {
            return null;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        return [
            'name' => (string) ($file['name'] ?? ''),
            'tmpName' => $file['tmp_name'],
            'size' => (int) $file['size'],
        ];
    }
}
