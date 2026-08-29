<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\VoiceConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pure validation tests over VoiceConfig::fromArray() — no disk I/O, no DB.
 * Mirrors AutoDraftConfigTest; covers docs/voice-announce-spec.md §7's
 * "missing section, missing key, empty value" checklist.
 */
final class VoiceConfigTest extends TestCase
{
    // Pool IDs here are deliberately dummy values. The real one lives only in
    // the gitignored db.ini: the settings endpoint is commish-gated precisely
    // to keep it off public surfaces, and committing it would undo that.
    public function testLoadsAValidSection(): void
    {
        $config = VoiceConfig::fromArray([
            'pool_id' => 'us-east-1:00000000-0000-0000-0000-000000000000',
            'region' => 'us-east-1',
        ]);

        self::assertSame('us-east-1:00000000-0000-0000-0000-000000000000', $config->poolId());
        self::assertSame('us-east-1', $config->region());
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        // parse_ini_file keeps trailing spaces on an unquoted value, and a
        // pool ID with a stray space fails at Polly with an opaque error —
        // cheaper to trim here than to debug on draft day.
        $config = VoiceConfig::fromArray([
            'pool_id' => "  us-east-1:abc  ",
            'region' => " us-east-1 ",
        ]);

        self::assertSame('us-east-1:abc', $config->poolId());
        self::assertSame('us-east-1', $config->region());
    }

    public function testRejectsAMissingSection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/\[Voice_Values\]/');

        VoiceConfig::fromArray(null);
    }

    public function testRejectsAMissingKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/region/');

        VoiceConfig::fromArray(['pool_id' => 'us-east-1:abc']);
    }

    public function testRejectsAnEmptyValue(): void
    {
        // The shipped db.ini.default has `pool_id=` with no value, so this is
        // the state every un-provisioned host is actually in.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/pool_id/');

        VoiceConfig::fromArray(['pool_id' => '', 'region' => 'us-east-1']);
    }

    public function testRejectsAWhitespaceOnlyValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/pool_id/');

        VoiceConfig::fromArray(['pool_id' => '   ', 'region' => 'us-east-1']);
    }

    public function testNamesTheSourceFileInTheMessage(): void
    {
        // The message is surfaced to the commish verbatim as `configError`,
        // so it has to say which file to go fix.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#/etc/wmffl/db\.ini#');

        VoiceConfig::fromArray(null, '/etc/wmffl/db.ini');
    }
}
