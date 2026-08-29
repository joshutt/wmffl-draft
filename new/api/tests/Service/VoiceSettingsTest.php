<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\PollyVoice;
use App\Repository\ConfigRepository;
use App\Service\DraftStateService;
use App\Tests\Support\DbTestCase;

/**
 * DraftStateService's `draft.voice.*` accessors against the real config
 * table — docs/voice-announce-spec.md §7. Uses the DB rather than a mock
 * because the thing worth proving is the defaults-when-absent behavior,
 * which is about what a real empty table reads back as.
 */
final class VoiceSettingsTest extends DbTestCase
{
    private DraftStateService $draftState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->draftState = new DraftStateService();
    }

    public function testDefaultsWhenNoKeysHaveEverBeenWritten(): void
    {
        self::assertSame([
            'enabled' => false,
            'voiceId' => 'Matthew',
            'startRound' => 1,
            'startPick' => 1,
        ], $this->draftState->getVoiceSettings());
    }

    public function testEnabledRoundTrips(): void
    {
        $this->draftState->setVoiceEnabled(true);
        self::assertTrue($this->draftState->getVoiceSettings()['enabled']);

        $this->draftState->setVoiceEnabled(false);
        self::assertFalse($this->draftState->getVoiceSettings()['enabled']);
    }

    public function testVoiceIdRoundTrips(): void
    {
        $this->draftState->setVoiceId(PollyVoice::Joanna);

        self::assertSame('Joanna', $this->draftState->getVoiceSettings()['voiceId']);
    }

    public function testStartRoundAndPickRoundTrip(): void
    {
        $this->draftState->setVoiceStart(7, 4);

        $settings = $this->draftState->getVoiceSettings();
        self::assertSame(7, $settings['startRound']);
        self::assertSame(4, $settings['startPick']);
    }

    public function testStartIsClampedToAtLeastOne(): void
    {
        $this->draftState->setVoiceStart(0, -3);

        $settings = $this->draftState->getVoiceSettings();
        self::assertSame(1, $settings['startRound']);
        self::assertSame(1, $settings['startPick']);
    }

    public function testWritingOneSettingLeavesTheOthersAlone(): void
    {
        // The console saves one field at a time (checkbox on change, number
        // inputs on blur), so a partial write must not reset its neighbours.
        $this->draftState->setVoiceEnabled(true);
        $this->draftState->setVoiceId(PollyVoice::Ivy);
        $this->draftState->setVoiceStart(3, 9);

        $this->draftState->setVoiceEnabled(false);

        self::assertSame([
            'enabled' => false,
            'voiceId' => 'Ivy',
            'startRound' => 3,
            'startPick' => 9,
        ], $this->draftState->getVoiceSettings());
    }

    public function testFallsBackToTheDefaultVoiceWhenTheStoredOneIsUnknown(): void
    {
        // A voice retired from the whitelist (or a hand-edited config row)
        // must not make every announcement of the draft fail at Polly.
        (new ConfigRepository())->set('draft.voice.voiceId', 'Brian');

        self::assertSame('Matthew', $this->draftState->getVoiceSettings()['voiceId']);
    }

    public function testVoiceSettingsDoNotLeakIntoTheTeamClockPrefixScan(): void
    {
        // getAllTeamRemainingSeconds() prefix-scans `draft.` keys by
        // `draft.team.`; a sibling `draft.voice.` key must not be picked up
        // by it as a team with id 0.
        $this->draftState->setVoiceEnabled(true);
        $this->draftState->setTeamRemainingSeconds(4, 600);

        self::assertSame([4 => 600], $this->draftState->getAllTeamRemainingSeconds());
    }
}
