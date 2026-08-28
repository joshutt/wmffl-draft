<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;
use PDO;
use Throwable;

/**
 * Port of the `autodraft_priority` table — the commish's ranked per-position
 * pick lists that drive AutoDraftService's player selection (see
 * docs/auto-draft-spec.md §6).
 */
class PriorityListRepository
{
    /**
     * The first available (undrafted) player on $pos's list, in rank order
     * — the "Selection query" from docs/auto-draft-spec.md §6. Only matched
     * rows (playerid IS NOT NULL) are eligible; pending rows are skipped.
     * Availability is the absence of an active `roster` row — nothing else.
     */
    public function firstAvailable(string $pos): ?int
    {
        $stmt = Db::connection()->prepare(
            'SELECT ap.playerid
             FROM autodraft_priority ap
             LEFT JOIN roster r ON r.PlayerID = ap.playerid AND r.DateOff IS NULL
             WHERE ap.pos = ? AND ap.playerid IS NOT NULL AND r.TeamID IS NULL
             ORDER BY ap.rank
             LIMIT 1'
        );
        $stmt->execute([$pos]);
        $playerId = $stmt->fetchColumn();

        return $playerId === false ? null : (int) $playerId;
    }

    /**
     * Replaces the priority list for every position present in $rows,
     * leaving other positions' lists untouched — the CSV upload's "replaces
     * the list for every position present in the file" rule
     * (docs/auto-draft-spec.md §6). Matching is name+pos against `players`,
     * case-insensitive/trimmed, restricted to usePos=1; a row matching zero
     * or more than one player is stored as pending (playerid NULL) rather
     * than failing the whole import.
     *
     * @param list<array{pos:string,rank:int,firstName:?string,lastName:string}> $rows
     * @return array{imported:int, pending:list<array{pos:string,rank:int,name:string,reason:string}>, counts:array<string,int>}
     */
    public function replace(array $rows): array
    {
        $pdo = Db::connection();
        $positions = array_values(array_unique(array_map(static fn (array $r): string => $r['pos'], $rows)));

        $pdo->beginTransaction();
        try {
            if ($positions !== []) {
                $placeholders = implode(',', array_fill(0, count($positions), '?'));
                $pdo->prepare("DELETE FROM autodraft_priority WHERE pos IN ({$placeholders})")
                    ->execute($positions);
            }

            $insert = $pdo->prepare(
                'INSERT INTO autodraft_priority (pos, rank, playerid, firstname, lastname)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $pending = [];
            foreach ($rows as $row) {
                $matches = $this->findMatchingPlayerIds($row['firstName'], $row['lastName'], $row['pos']);
                $playerId = count($matches) === 1 ? $matches[0] : null;

                $insert->execute([$row['pos'], $row['rank'], $playerId, $row['firstName'], $row['lastName']]);

                if ($playerId === null) {
                    $pending[] = [
                        'pos' => $row['pos'],
                        'rank' => $row['rank'],
                        'name' => $this->displayName($row['firstName'], $row['lastName']),
                        'reason' => $matches === [] ? 'no matching player' : 'matches multiple players',
                    ];
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'imported' => count($rows) - count($pending),
            'pending' => $pending,
            'counts' => $this->countsByPosition(),
        ];
    }

    /**
     * Re-attempts name+pos matching for every pending row. Runs on upload
     * and at the start of each auto-pick — the table is small enough that
     * this is free — so a player added to `players` after the upload
     * resolves automatically with no re-upload.
     */
    public function resolvePending(): void
    {
        $pdo = Db::connection();
        $pending = $pdo->query(
            'SELECT id, pos, firstname, lastname FROM autodraft_priority WHERE playerid IS NULL'
        )->fetchAll();

        if ($pending === []) {
            return;
        }

        $update = $pdo->prepare('UPDATE autodraft_priority SET playerid = ? WHERE id = ?');
        foreach ($pending as $row) {
            $matches = $this->findMatchingPlayerIds($row['firstname'], $row['lastname'], $row['pos']);
            if (count($matches) === 1) {
                $update->execute([$matches[0], $row['id']]);
            }
        }
    }

    /** @return array<string,int> pos => count of matched (non-pending) entries */
    public function countsByPosition(): array
    {
        $stmt = Db::connection()->query(
            'SELECT pos, COUNT(*) AS cnt FROM autodraft_priority WHERE playerid IS NOT NULL GROUP BY pos'
        );

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['pos']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Every pending (unmatched) row, for the commish's pre-draft-day review
     * — docs/auto-draft-spec.md §7's GET endpoint.
     *
     * @return list<array{pos:string,rank:int,name:string,reason:string}>
     */
    public function pendingRows(): array
    {
        $stmt = Db::connection()->query(
            'SELECT pos, rank, firstname, lastname FROM autodraft_priority
             WHERE playerid IS NULL ORDER BY pos, rank'
        );

        $pending = [];
        foreach ($stmt->fetchAll() as $row) {
            $matches = $this->findMatchingPlayerIds($row['firstname'], $row['lastname'], $row['pos']);
            $pending[] = [
                'pos' => $row['pos'],
                'rank' => (int) $row['rank'],
                'name' => $this->displayName($row['firstname'], $row['lastname']),
                'reason' => $matches === [] ? 'no matching player' : 'matches multiple players',
            ];
        }

        return $pending;
    }

    /** @return list<int> matching players.playerid values (0, 1, or many) */
    private function findMatchingPlayerIds(?string $firstName, string $lastName, string $pos): array
    {
        $firstName = $firstName !== null ? trim($firstName) : '';

        $sql = 'SELECT playerid FROM players
                WHERE LOWER(TRIM(lastname)) = LOWER(TRIM(?)) AND pos = ? AND usePos = 1';
        $params = [$lastName, $pos];

        if ($firstName !== '') {
            $sql .= ' AND LOWER(TRIM(firstname)) = LOWER(TRIM(?))';
            $params[] = $firstName;
        }

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function displayName(?string $firstName, string $lastName): string
    {
        return trim(($firstName ?? '') . ' ' . $lastName);
    }
}
