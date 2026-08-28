<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\AutoDraftConfig;
use App\Domain\Position;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pure validation tests over AutoDraftConfig::fromArray() — no disk I/O, no
 * DB — per docs/auto-draft-spec.md §11's config-validation checklist.
 */
final class AutoDraftConfigTest extends TestCase
{
    /** @return array<string,int> */
    private function validAllocations(): array
    {
        return array_fill_keys(Position::codes(), 3);
    }

    /** @return array<string,int> */
    private function validWeightRow(): array
    {
        return array_fill_keys(Position::codes(), 1);
    }

    public function testLoadsAValidTwoRoundConfig(): void
    {
        $config = AutoDraftConfig::fromArray([
            'allocations' => $this->validAllocations(),
            'weights' => ['1' => $this->validWeightRow(), '2' => $this->validWeightRow()],
        ], 2);

        self::assertSame($this->validAllocations(), $config->allocations());
        self::assertSame($this->validWeightRow(), $config->weightsForRound(1));
        self::assertSame($this->validWeightRow(), $config->weightsForRound(2));
    }

    public function testRejectsAMissingRound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/round 2/');

        AutoDraftConfig::fromArray([
            'allocations' => $this->validAllocations(),
            'weights' => ['1' => $this->validWeightRow()],
        ], 2);
    }

    public function testRejectsARoundMissingAPosition(): void
    {
        $row = $this->validWeightRow();
        unset($row['K']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/K/');

        AutoDraftConfig::fromArray([
            'allocations' => $this->validAllocations(),
            'weights' => ['1' => $row],
        ], 1);
    }

    public function testRejectsANegativeWeight(): void
    {
        $row = $this->validWeightRow();
        $row['K'] = -1;

        $this->expectException(RuntimeException::class);

        AutoDraftConfig::fromArray([
            'allocations' => $this->validAllocations(),
            'weights' => ['1' => $row],
        ], 1);
    }

    public function testRejectsAnAllZeroRound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/weight 0/');

        AutoDraftConfig::fromArray([
            'allocations' => $this->validAllocations(),
            'weights' => ['1' => array_fill_keys(Position::codes(), 0)],
        ], 1);
    }

    public function testRejectsAnAllocationLimitBelowOne(): void
    {
        $allocations = $this->validAllocations();
        $allocations['K'] = 0;

        $this->expectException(RuntimeException::class);

        AutoDraftConfig::fromArray([
            'allocations' => $allocations,
            'weights' => ['1' => $this->validWeightRow()],
        ], 1);
    }

    public function testRejectsAMissingAllocationsKey(): void
    {
        $this->expectException(RuntimeException::class);

        AutoDraftConfig::fromArray(['weights' => ['1' => $this->validWeightRow()]], 1);
    }
}
