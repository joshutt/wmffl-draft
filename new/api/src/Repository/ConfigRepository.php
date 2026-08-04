<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Raw access to the generic key-value `config` table. Route handlers and
 * services must not build `draft.*` key strings themselves — that belongs in
 * App\Service\DraftStateService (see docs/modernization-spec.md §3's note on
 * clockService.php's explode('.', $key) parsing being error-prone).
 */
class ConfigRepository
{
    public function get(string $key): ?string
    {
        $stmt = Db::connection()->prepare('SELECT value FROM config WHERE `key` = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $stmt = Db::connection()->prepare('REPLACE INTO config (`key`, `value`) VALUES (?, ?)');
        $stmt->execute([$key, $value]);
    }

    /**
     * @return array<string, string> full key => value, for every key starting with $prefix
     */
    public function getByPrefix(string $prefix): array
    {
        $stmt = Db::connection()->prepare('SELECT `key`, `value` FROM config WHERE `key` LIKE ?');
        $stmt->execute([$prefix . '%']);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }
}
