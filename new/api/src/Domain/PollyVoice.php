<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The Polly voices the announcer is allowed to use — the eight neural-engine
 * voices the `src/voice.html` prototype offered (see
 * docs/voice-announce-spec.md §4.2).
 *
 * This exists as a whitelist rather than a free-text setting because the
 * announcer page sends `VoiceId` straight to Polly. The announcement `Text`
 * is built server-side from draft data, but the voice would otherwise be
 * client-chosen, so it gets validated on the way into the config table
 * instead of trusting whatever the commish console posts.
 *
 * All eight must stay valid for `Engine: 'neural'` — useAnnouncer.ts requests
 * the neural engine unconditionally, and Polly rejects a voice that has no
 * neural build.
 */
enum PollyVoice: string
{
    case Joanna = 'Joanna';
    case Matthew = 'Matthew';
    case Ivy = 'Ivy';
    case Justin = 'Justin';
    case Kendra = 'Kendra';
    case Kimberly = 'Kimberly';
    case Salli = 'Salli';
    case Joey = 'Joey';

    /** The voice used when `draft.voice.voiceId` has never been set. */
    public const DEFAULT = self::Matthew;

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(static fn (self $v): string => $v->value, self::cases());
    }
}
