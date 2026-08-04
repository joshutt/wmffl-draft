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
    private const KEY_TEAM_PREFIX = 'draft.team.';
    private const KEY_LOGIN_PREFIX = 'draft.login.';

    public function __construct(private readonly ConfigRepository $config = new ConfigRepository())
    {
    }

    public function isDraftStarted(): bool
    {
        return $this->config->get(self::KEY_START) === 'true';
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
}
