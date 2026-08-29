<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Loads the `[Voice_Values]` section of config/db.ini — the Cognito identity
 * pool and AWS region the announcer page uses to call Amazon Polly from the
 * browser (see docs/voice-announce-spec.md §3).
 *
 * These live in db.ini rather than the `config` table because they are
 * per-environment infrastructure, hand-provisioned and gitignored, exactly
 * like `[DB_Values]`. The commish-flippable settings (on/off, voice, start
 * round/pick) are the opposite kind of value and live in the DB — see
 * App\Service\DraftStateService.
 *
 * Db::loadConfig() reads the same file but is deliberately NOT refactored
 * onto a shared loader, for the reason AutoDraftConfig already documents:
 * the two validate different things, and a bad refactor here would break DB
 * connectivity for the entire app rather than just the announcer.
 */
final class VoiceConfig
{
    private function __construct(
        private readonly string $poolId,
        private readonly string $region,
    ) {
    }

    /**
     * Reads config/db.ini off disk.
     *
     * Throws rather than returning a null object: every caller so far
     * (VoiceController) catches it and surfaces the message to the commish
     * as `configError`, which is more useful than a silently voiceless page.
     */
    public static function load(): self
    {
        $path = __DIR__ . '/../../config/db.ini';

        if (!is_file($path)) {
            throw new RuntimeException(
                "Missing {$path} — copy config/db.ini.default to config/db.ini and fill in [Voice_Values]."
            );
        }

        $parsed = parse_ini_file($path, true);

        if ($parsed === false) {
            throw new RuntimeException("Could not parse {$path}.");
        }

        return self::fromArray($parsed['Voice_Values'] ?? null, $path);
    }

    /**
     * Pure validation over an already-parsed `[Voice_Values]` section, split
     * out from load() the same way AutoDraftConfig::fromArray() is, so tests
     * can exercise malformed sections without touching disk.
     *
     * @param array<mixed>|null $section
     */
    public static function fromArray(?array $section, string $source = 'db.ini'): self
    {
        if ($section === null) {
            throw new RuntimeException(
                "{$source} is missing a [Voice_Values] section — see docs/voice-announce-spec.md §3."
            );
        }

        $values = [];
        foreach (['pool_id', 'region'] as $key) {
            $value = $section[$key] ?? null;

            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException("{$source} is missing a value for Voice_Values.{$key}.");
            }

            $values[$key] = trim($value);
        }

        return new self($values['pool_id'], $values['region']);
    }

    /** The Cognito identity pool ID, e.g. `us-east-1:xxxxxxxx-…`. */
    public function poolId(): string
    {
        return $this->poolId;
    }

    /** The AWS region Polly is called in, e.g. `us-east-1`. */
    public function region(): string
    {
        return $this->region;
    }
}
