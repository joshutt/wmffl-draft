<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of the `roster` table checks in DraftUtils.php's confirmPlayerAvailable()
 * and the INSERT in makePick().
 */
class RosterRepository
{
    public function isPlayerAvailable(int $playerId): bool
    {
        return !$this->hasActiveRosterRow($playerId, forUpdate: false);
    }

    /**
     * Same check as isPlayerAvailable(), but with SELECT ... FOR UPDATE so it
     * participates in PickService's transaction lock — the InnoDB gap lock on
     * a non-matching row here is what stops a concurrent transaction from
     * inserting a matching one before this transaction commits (see
     * docs/modernization-spec.md §6).
     */
    public function isPlayerAvailableForUpdate(int $playerId): bool
    {
        return !$this->hasActiveRosterRow($playerId, forUpdate: true);
    }

    private function hasActiveRosterRow(int $playerId, bool $forUpdate): bool
    {
        $sql = 'SELECT PlayerID FROM roster WHERE PlayerID = ? AND DateOff IS NULL';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([$playerId]);

        return $stmt->fetchColumn() !== false;
    }

    public function addPick(int $playerId, int $teamId): void
    {
        $stmt = Db::connection()->prepare(
            'INSERT INTO roster (PlayerID, TeamID, DateOn) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$playerId, $teamId]);
    }
}
