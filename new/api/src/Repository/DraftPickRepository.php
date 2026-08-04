<?php

declare(strict_types=1);

namespace App\Repository;

use App\Db;

/**
 * Port of the `draftpicks` table access scattered across
 * DraftUtils.php / clock.class.php / picks.php.
 */
class DraftPickRepository
{
    /**
     * The next unfilled pick for the season, ordered by round/pick.
     * No row lock — for display/read purposes only. See findOpenPickForUpdate()
     * for the version used inside a pick-submission transaction.
     *
     * @return array{round:int,pick:int,teamId:int}|null
     */
    public function findOpenPick(int $season): ?array
    {
        return $this->fetchOpenPick($season, forUpdate: false);
    }

    /**
     * Same as findOpenPick(), but with SELECT ... FOR UPDATE so it can be
     * used inside PickService's transaction to serialize concurrent pick
     * submissions against the same open pick (docs/modernization-spec.md §6).
     *
     * @return array{round:int,pick:int,teamId:int}|null
     */
    public function findOpenPickForUpdate(int $season): ?array
    {
        return $this->fetchOpenPick($season, forUpdate: true);
    }

    /**
     * @return array{round:int,pick:int,teamId:int}|null
     */
    private function fetchOpenPick(int $season, bool $forUpdate): ?array
    {
        $sql = 'SELECT Round, Pick, teamid FROM draftpicks
                WHERE Season = ? AND playerid IS NULL
                ORDER BY Round, Pick LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([$season]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'round' => (int) $row['Round'],
            'pick' => (int) $row['Pick'],
            'teamId' => (int) $row['teamid'],
        ];
    }

    public function markPicked(int $season, int $round, int $pick, int $playerId): void
    {
        $stmt = Db::connection()->prepare(
            'UPDATE draftpicks SET playerid = ? WHERE Season = ? AND Round = ? AND Pick = ?'
        );
        $stmt->execute([$playerId, $season, $round, $pick]);
    }

    /**
     * The most recent filled pick — undopick.php's target row.
     *
     * @return array{round:int,pick:int,teamId:int,playerId:int}|null
     */
    public function findLastFilledPick(int $season): ?array
    {
        $stmt = Db::connection()->prepare(
            'SELECT Round, Pick, teamid, playerid FROM draftpicks
             WHERE Season = ? AND playerid IS NOT NULL
             ORDER BY Round DESC, Pick DESC LIMIT 1'
        );
        $stmt->execute([$season]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'round' => (int) $row['Round'],
            'pick' => (int) $row['Pick'],
            'teamId' => (int) $row['teamid'],
            'playerId' => (int) $row['playerid'],
        ];
    }

    /** Reopens a pick — undopick.php's `set playerid=null`. */
    public function clearPick(int $season, int $round, int $pick): void
    {
        $stmt = Db::connection()->prepare(
            'UPDATE draftpicks SET playerid = NULL WHERE Season = ? AND Round = ? AND Pick = ?'
        );
        $stmt->execute([$season, $round, $pick]);
    }

    /** The team holding a specific pick slot (e.g. round 1 pick 1). */
    public function findTeamForPick(int $season, int $round, int $pick): ?int
    {
        $stmt = Db::connection()->prepare(
            'SELECT teamid FROM draftpicks WHERE Season = ? AND Round = ? AND Pick = ?'
        );
        $stmt->execute([$season, $round, $pick]);
        $teamId = $stmt->fetchColumn();

        return $teamId === false || $teamId === null ? null : (int) $teamId;
    }

    /**
     * The earliest round in which this team still has an open pick — feeds
     * the autopick round-based position heuristic (commish/autopick.php).
     */
    public function minOpenRoundForTeam(int $teamId, int $season): ?int
    {
        $stmt = Db::connection()->prepare(
            'SELECT MIN(Round) FROM draftpicks
             WHERE playerid IS NULL AND teamid = ? AND Season = ?'
        );
        $stmt->execute([$teamId, $season]);
        $round = $stmt->fetchColumn();

        return $round === false || $round === null ? null : (int) $round;
    }

    /**
     * Every pick for the season, in round/pick order, joined with the
     * picking team's name and (when filled) the selected player's info.
     * Backs GET /api/draft/board — replaces picks.php's per-row DataObjects
     * getLink() calls with a single query.
     *
     * @return list<array{
     *     round:int, pick:int, teamId:int, teamName:string,
     *     playerId:?int, playerName:?string, playerPos:?string, playerTeam:?string,
     *     pickTime:?int
     * }>
     */
    public function findAllForSeason(int $season): array
    {
        $stmt = Db::connection()->prepare(
            'SELECT d.Round, d.Pick, d.teamid, t.name AS teamName,
                    d.playerid, p.firstname, p.lastname, p.pos,
                    r.nflteamid, d.pickTime
             FROM draftpicks d
             JOIN teamnames t ON t.teamid = d.teamid AND t.season = d.Season
             LEFT JOIN newplayers p ON p.playerid = d.playerid
             LEFT JOIN nflrosters r ON r.playerid = d.playerid AND r.dateoff IS NULL
             WHERE d.Season = ?
             ORDER BY d.Round, d.Pick'
        );
        $stmt->execute([$season]);

        $picks = [];
        foreach ($stmt->fetchAll() as $row) {
            $picks[] = [
                'round' => (int) $row['Round'],
                'pick' => (int) $row['Pick'],
                'teamId' => (int) $row['teamid'],
                'teamName' => $row['teamName'],
                'playerId' => $row['playerid'] !== null ? (int) $row['playerid'] : null,
                'playerName' => $row['playerid'] !== null ? "{$row['firstname']} {$row['lastname']}" : null,
                'playerPos' => $row['pos'],
                'playerTeam' => $row['nflteamid'],
                'pickTime' => $row['pickTime'] !== null ? strtotime((string) $row['pickTime']) : null,
            ];
        }

        return $picks;
    }

    /**
     * Latest pick timestamp across all picks — no season filter, matching
     * clock.class.php:getPreviousPickTime()'s (unfiltered) query exactly.
     */
    public function maxPickTimestamp(): ?int
    {
        $stmt = Db::connection()->query('SELECT MAX(pickTime) AS maxPickTime FROM draftpicks');
        $value = $stmt->fetchColumn();

        return $value !== null && $value !== false ? strtotime((string) $value) : null;
    }
}
