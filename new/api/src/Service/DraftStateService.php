<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ConfigRepository;

/**
 * Typed wrapper around the `draft.*` keys in the generic `config` table.
 * Centralizes the key strings so nothing else in the app builds them by hand
 * (docs/modernization-spec.md §3 — this is the fix for clockService.php's
 * `explode('.', $key)` parsing and similar ad hoc key-building).
 */
class DraftStateService
{
    private const KEY_START = 'draft.start';
    private const KEY_CLOCK_RUN = 'draft.clock.run';
    private const KEY_CLOCK_START = 'draft.clock.start';
    private const KEY_FULL_START = 'draft.full.start';
    private const KEY_CLOCK_MAX_TIME = 'draft.clock.maxTime';
    private const KEY_CLOCK_ADD_TIME = 'draft.clock.addTime';
    private const KEY_CLOCK_ALLOWED = 'draft.clock.allowed';
    private const KEY_TEAM_PREFIX = 'draft.team.';
    private const KEY_LOGIN_PREFIX = 'draft.login.';

    public function __construct(private readonly ConfigRepository $config = new ConfigRepository())
    {
    }

    public function isDraftStarted(): bool
    {
        return $this->config->get(self::KEY_START) === 'true';
    }

    public function setDraftStarted(bool $started): void
    {
        $this->config->set(self::KEY_START, $started ? 'true' : 'false');
    }

    public function isClockRunning(): bool
    {
        return $this->config->get(self::KEY_CLOCK_RUN) === 'true';
    }

    public function setClockRunning(bool $running): void
    {
        $this->config->set(self::KEY_CLOCK_RUN, $running ? 'true' : 'false');
    }

    /** Unix timestamp the clock was (re)started, or null if never set. */
    public function getClockStartTimestamp(): ?int
    {
        $value = $this->config->get(self::KEY_CLOCK_START);

        return $value !== null ? (int) $value : null;
    }

    public function setClockStartTimestamp(int $timestamp): void
    {
        $this->config->set(self::KEY_CLOCK_START, (string) $timestamp);
    }

    public function setFullStartTimestamp(int $timestamp): void
    {
        $this->config->set(self::KEY_FULL_START, (string) $timestamp);
    }

    /** Unix timestamp the draft as a whole started, or null if never set. */
    public function getFullStartTimestamp(): ?int
    {
        $value = $this->config->get(self::KEY_FULL_START);

        return $value !== null ? (int) $value : null;
    }

    public function getMaxTimeSeconds(): int
    {
        return (int) ($this->config->get(self::KEY_CLOCK_MAX_TIME) ?? 0);
    }

    public function getAddTimeSeconds(): int
    {
        return (int) ($this->config->get(self::KEY_CLOCK_ADD_TIME) ?? 0);
    }

    public function getTeamRemainingSeconds(int $teamId): int
    {
        return (int) ($this->config->get(self::KEY_TEAM_PREFIX . $teamId) ?? 0);
    }

    public function setTeamRemainingSeconds(int $teamId, int $seconds): void
    {
        $this->config->set(self::KEY_TEAM_PREFIX . $teamId, (string) $seconds);
    }

    /**
     * All teams' remaining clock, keyed by teamId — for the board's
     * per-team clock list. Backed by one prefix scan instead of N lookups.
     *
     * @return array<int, int> teamId => seconds remaining
     */
    public function getAllTeamRemainingSeconds(): array
    {
        $result = [];
        foreach ($this->config->getByPrefix(self::KEY_TEAM_PREFIX) as $key => $value) {
            $teamId = (int) substr($key, strlen(self::KEY_TEAM_PREFIX));
            $result[$teamId] = (int) $value;
        }

        return $result;
    }

    public function recordLoginHeartbeat(int $userId): void
    {
        $this->config->set(self::KEY_LOGIN_PREFIX . $userId, date('Y-m-d H:i:s'));
    }

    /**
     * Every user's last-seen heartbeat, keyed by userId — for the commish
     * presence table.
     *
     * @return array<int, string> userId => 'Y-m-d H:i:s' last-seen
     */
    public function getAllLoginHeartbeats(): array
    {
        $result = [];
        foreach ($this->config->getByPrefix(self::KEY_LOGIN_PREFIX) as $key => $value) {
            $userId = (int) substr($key, strlen(self::KEY_LOGIN_PREFIX));
            $result[$userId] = $value;
        }

        return $result;
    }

    /** The per-team clock budget every team starts the draft with. */
    public function getAllowedSeconds(): int
    {
        return (int) ($this->config->get(self::KEY_CLOCK_ALLOWED) ?? 0);
    }

    /**
     * Resets every existing draft.team.<id> clock to the same value — port of
     * startDraft.php's `UPDATE config ... WHERE key LIKE 'draft.team.%'`.
     */
    public function resetAllTeamClocks(int $seconds): void
    {
        foreach (array_keys($this->getAllTeamRemainingSeconds()) as $teamId) {
            $this->setTeamRemainingSeconds($teamId, $seconds);
        }
    }
}
