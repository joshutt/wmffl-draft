<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config\VoiceConfig;
use App\Domain\PollyVoice;
use App\Http\Guard;
use App\Http\Request;
use App\Http\Response;
use App\Service\DraftStateService;
use RuntimeException;

/**
 * Voice-announcer settings — read by the /announcer page, written by the
 * commish console's Voice Announcer panel (docs/voice-announce-spec.md §4.4).
 *
 * Both routes are behind Guard::requireCommish(). The GET is not public even
 * though the announcer page is "just a board": the response carries the
 * Cognito pool ID, and that pool allows unauthenticated Polly calls, so a
 * public endpoint would hand anyone who found it the ability to spend money
 * against this account.
 */
final class VoiceController
{
    public function __construct(
        private readonly DraftStateService $draftState = new DraftStateService(),
    ) {
    }

    /** GET /api/commish/voice */
    public function show(): void
    {
        Guard::requireCommish();

        Response::json($this->payload());
    }

    /**
     * POST /api/commish/voice {enabled?, voiceId?, startRound?, startPick?}
     *
     * A partial update — only the keys actually present in the body are
     * written, so the commish console can save one field on blur without
     * having to round-trip the others and risk clobbering a concurrent edit
     * from the announcer machine.
     */
    public function update(): void
    {
        Guard::requireCommish();

        $body = Request::json();

        if (array_key_exists('enabled', $body)) {
            if (!is_bool($body['enabled'])) {
                Response::error('enabled must be a boolean', 400);
            }

            $this->draftState->setVoiceEnabled($body['enabled']);
        }

        if (array_key_exists('voiceId', $body)) {
            $voice = is_string($body['voiceId']) ? PollyVoice::tryFrom($body['voiceId']) : null;
            if ($voice === null) {
                Response::error(
                    'voiceId must be one of: ' . implode(', ', PollyVoice::names()),
                    400
                );
            }

            $this->draftState->setVoiceId($voice);
        }

        // Round and pick move together — they're one logical position in the
        // draft, and writing a new round against a stale pick would point the
        // announcer at a slot the commish never chose.
        if (array_key_exists('startRound', $body) || array_key_exists('startPick', $body)) {
            $current = $this->draftState->getVoiceSettings();
            $round = self::positiveInt($body['startRound'] ?? $current['startRound'], 'startRound');
            $pick = self::positiveInt($body['startPick'] ?? $current['startPick'], 'startPick');

            $this->draftState->setVoiceStart($round, $pick);
        }

        Response::json($this->payload());
    }

    /**
     * The single response shape both routes return, so the console can treat
     * a save's response as the new authoritative state without a refetch.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $settings = $this->draftState->getVoiceSettings();

        $poolId = null;
        $region = null;
        $configError = null;

        // A host whose db.ini predates this feature has no [Voice_Values].
        // That's a "you can't announce yet" state, not a 500 — the commish
        // still needs the rest of this panel to render so they can see why.
        try {
            $voiceConfig = VoiceConfig::load();
            $poolId = $voiceConfig->poolId();
            $region = $voiceConfig->region();
        } catch (RuntimeException $e) {
            $configError = $e->getMessage();
        }

        return $settings + [
            'poolId' => $poolId,
            'region' => $region,
            'voices' => PollyVoice::names(),
            'configError' => $configError,
        ];
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
        } else {
            Response::error("{$field} must be an integer >= 1", 400);
        }

        if ($int < 1) {
            Response::error("{$field} must be an integer >= 1", 400);
        }

        return $int;
    }
}
