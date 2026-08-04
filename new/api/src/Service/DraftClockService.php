<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClockStopRepository;
use App\Repository\DraftPickRepository;

/**
 * Port of clock.class.php + DraftUtils.php's getTeamOnClock()/adjustClock() —
 * read both together before changing this, per docs/modernization-spec.md's
 * warning that the elapsed-time math depends on draftclockstop rows layered
 * on top of the config-table timestamps.
 */
final class DraftClockService
{
    public function __construct(
        private readonly DraftPickRepository $draftPicks = new DraftPickRepository(),
        private readonly ClockStopRepository $clockStops = new ClockStopRepository(),
        private readonly DraftStateService $draftState = new DraftStateService(),
    ) {
    }

    /**
     * @return array{round:int,pick:int,teamId:int}|null
     */
    public function getCurrentPick(int $season): ?array
    {
        return $this->draftPicks->findOpenPick($season);
    }

    /**
     * The team currently allowed to pick, or null if the draft hasn't been
     * started or there's no open pick left (draft complete).
     */
    public function getTeamOnClock(int $season): ?int
    {
        if (!$this->draftState->isDraftStarted()) {
            return null;
        }

        return $this->draftPicks->findOpenPick($season)['teamId'] ?? null;
    }

    public function isClockRunning(): bool
    {
        return $this->draftState->isClockRunning();
    }

    public function getTimeAvail(int $teamId): int
    {
        return $this->draftState->getTeamRemainingSeconds($teamId);
    }

    /**
     * Port of clock.class.php:getPreviousPickTime() — the later of the last
     * pick made and the draft's overall start time. No season filter, same
     * as the original (draftpicks rows from past seasons are already in the
     * past, so this doesn't matter in practice for a live draft).
     */
    public function getPreviousPickTime(): int
    {
        $pickTime = $this->draftPicks->maxPickTimestamp() ?? 0;
        $startTime = $this->draftState->getFullStartTimestamp() ?? 0;

        return max($pickTime, $startTime);
    }

    /**
     * Total paused seconds recorded against this pick in draftclockstop.
     */
    public function getExtraTime(int $season, int $round, int $pick): int
    {
        return $this->clockStops->sumExtraSeconds($season, $round, $pick);
    }

    /**
     * Seconds elapsed on the current pick's clock: wall-clock time since the
     * last pick (or draft start), minus any manually-paused time — port of
     * clock.class.php:getTotalTimeUsed().
     */
    public function getTotalTimeUsed(int $season, ?int $round = null, ?int $pick = null): int
    {
        if ($round === null || $pick === null) {
            $current = $this->getCurrentPick($season);
            $round = $current['round'] ?? 0;
            $pick = $current['pick'] ?? 0;
        }

        $extra = ($round === 1 && $pick === 1) ? 0 : $this->getExtraTime($season, $round, $pick);
        $prev = $this->getPreviousPickTime();

        return time() - $prev - $extra;
    }

    /**
     * Deducts the time used from the picking team's remaining clock (clamped
     * to [0, maxTime]) and credits the increment to whichever team is now on
     * the clock (clamped to maxTime) — port of DraftUtils.php:adjustClock().
     *
     * Must be called AFTER the pick has been recorded (draftpicks.playerid
     * set), since it re-queries getTeamOnClock() to find the *next* team —
     * same ordering dependency as the legacy code.
     */
    public function adjustClock(int $season, int $teamId, int $totalTimeUsed): void
    {
        $maxTime = $this->draftState->getMaxTimeSeconds();
        $addTime = $this->draftState->getAddTimeSeconds();

        $current = $this->draftState->getTeamRemainingSeconds($teamId);
        $candidate = min($current - $totalTimeUsed, $maxTime);
        $this->draftState->setTeamRemainingSeconds($teamId, max($candidate, 0));

        $nextTeam = $this->getTeamOnClock($season);
        if ($nextTeam !== null) {
            $nextCurrent = $this->draftState->getTeamRemainingSeconds($nextTeam);
            $this->draftState->setTeamRemainingSeconds($nextTeam, min($nextCurrent + $addTime, $maxTime));
        }
    }
}
