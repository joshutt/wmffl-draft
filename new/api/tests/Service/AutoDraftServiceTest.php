<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Config\AutoDraftConfig;
use App\Domain\Position;
use App\Exception\PlayerNotFoundException;
use App\Repository\DraftPickRepository;
use App\Repository\PriorityListRepository;
use App\Repository\RosterRepository;
use App\Service\AutoDraftService;
use PHPUnit\Framework\TestCase;

/**
 * Pure logic tests for the algorithm in docs/auto-draft-spec.md §4 — every
 * dependency is mocked, no DB needed, mirroring DraftClockServiceTest's
 * style.
 */
final class AutoDraftServiceTest extends TestCase
{
    /** @param array<string,int> $weights */
    private function config(array $weights, ?array $allocations = null): AutoDraftConfig
    {
        return AutoDraftConfig::fromArray([
            'allocations' => $allocations ?? array_fill_keys(Position::codes(), 99),
            'weights' => ['1' => $weights],
        ], 1);
    }

    private function draftPicksOnRound(int $round): DraftPickRepository
    {
        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('minOpenRoundForTeam')->willReturn($round);

        return $draftPicks;
    }

    private function rosterWithCounts(array $counts): RosterRepository
    {
        $roster = $this->createMock(RosterRepository::class);
        $roster->method('countActiveByPosition')->willReturn($counts);

        return $roster;
    }

    public function testWeightedPickIsStrictlyProportionalAcrossTheFullRollRange(): void
    {
        $weights = array_fill_keys(Position::codes(), 0);
        $weights['QB'] = 1;
        $weights['RB'] = 3;
        $weights['WR'] = 6;

        $playerIdByPos = ['QB' => 100, 'RB' => 101, 'WR' => 102];

        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->method('firstAvailable')
            ->willReturnCallback(static fn (string $pos): ?int => $playerIdByPos[$pos] ?? null);

        $sum = array_sum($weights); // 10
        $rolls = range(0, $sum - 1);
        $rollIndex = 0;
        $randomizer = function (int $max) use ($sum, &$rolls, &$rollIndex): int {
            self::assertSame($sum - 1, $max, 'expected $max to be the full eligible-weight sum minus one');

            return $rolls[$rollIndex++];
        };

        $service = new AutoDraftService(
            $this->config($weights),
            $this->rosterWithCounts([]),
            $this->draftPicksOnRound(1),
            $priorityLists,
            $randomizer,
        );

        $picked = ['QB' => 0, 'RB' => 0, 'WR' => 0];
        foreach ($rolls as $_) {
            $playerId = $service->selectPlayer(2026, 1);
            $picked[array_search($playerId, $playerIdByPos, true)]++;
        }

        self::assertSame(['QB' => 1, 'RB' => 3, 'WR' => 6], $picked);
    }

    public function testPositionAtItsAllocationLimitIsNeverChosen(): void
    {
        $weights = array_fill_keys(Position::codes(), 0);
        $weights['RB'] = 5;
        $weights['WR'] = 5;

        $allocations = array_fill_keys(Position::codes(), 99);
        $allocations['RB'] = 2;

        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->method('firstAvailable')
            ->willReturnCallback(static fn (string $pos): ?int => $pos === 'WR' ? 200 : 999);

        // RB is already at its limit (2 of 2) — WR must be the only choice
        // no matter what the roll is, across the whole original 0..9 range.
        $randomizer = static fn (int $max): int => 3;

        $service = new AutoDraftService(
            $this->config($weights, $allocations),
            $this->rosterWithCounts(['RB' => 2]),
            $this->draftPicksOnRound(1),
            $priorityLists,
            $randomizer,
        );

        self::assertSame(200, $service->selectPlayer(2026, 1));
    }

    public function testAllPositionsBlockedBumpsAllocationLimitsUntilAPickIsMade(): void
    {
        $weights = array_fill_keys(Position::codes(), 0);
        $weights['RB'] = 1;

        $allocations = array_fill_keys(Position::codes(), 1);

        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->method('firstAvailable')
            ->willReturnCallback(static fn (string $pos): ?int => $pos === 'RB' ? 300 : null);

        $randomizer = static fn (int $max): int => 0;

        $service = new AutoDraftService(
            $this->config($weights, $allocations),
            // RB is already at its (only) allocation limit of 1 — every
            // position starts zeroed out and the limit must bump before
            // RB (the only weighted position) becomes eligible again.
            $this->rosterWithCounts(['RB' => 1]),
            $this->draftPicksOnRound(1),
            $priorityLists,
            $randomizer,
        );

        self::assertSame(300, $service->selectPlayer(2026, 1));
    }

    public function testAnExhaustedPriorityListReRollsToAnotherPositionRatherThanFailing(): void
    {
        $weights = array_fill_keys(Position::codes(), 0);
        $weights['RB'] = 1;
        $weights['WR'] = 1;

        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->expects($this->exactly(2))
            ->method('firstAvailable')
            ->willReturnCallback(static fn (string $pos): ?int => $pos === 'RB' ? null : 105);

        // Roll 0 always lands on the first still-eligible position in
        // QB..DB order: RB first (exhausted, re-roll), then WR.
        $randomizer = static fn (int $max): int => 0;

        $service = new AutoDraftService(
            $this->config($weights),
            $this->rosterWithCounts([]),
            $this->draftPicksOnRound(1),
            $priorityLists,
            $randomizer,
        );

        self::assertSame(105, $service->selectPlayer(2026, 1));
    }

    public function testAllListsExhaustedThrowsAndDoesNotHang(): void
    {
        $weights = array_fill_keys(Position::codes(), 1);

        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->method('firstAvailable')->willReturn(null);

        $randomizer = static fn (int $max): int => 0;

        $service = new AutoDraftService(
            $this->config($weights),
            $this->rosterWithCounts([]),
            $this->draftPicksOnRound(1),
            $priorityLists,
            $randomizer,
        );

        $this->expectException(PlayerNotFoundException::class);
        $service->selectPlayer(2026, 1);
    }

    public function testNoOpenPicksLeftThrows(): void
    {
        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->expects($this->never())->method('firstAvailable');

        // minOpenRoundForTeam() returning null must short-circuit before
        // ever consulting weights or priority lists.
        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('minOpenRoundForTeam')->willReturn(null);

        $service = new AutoDraftService(
            $this->config(array_fill_keys(Position::codes(), 1)),
            $this->rosterWithCounts([]),
            $draftPicks,
            $priorityLists,
        );

        $this->expectException(PlayerNotFoundException::class);
        $service->selectPlayer(2026, 1);
    }

    public function testSelectPlayerAtPositionIgnoresWeightsAndAllocations(): void
    {
        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->expects($this->once())
            ->method('firstAvailable')
            ->with('K')
            ->willReturn(400);

        $weights = array_fill_keys(Position::codes(), 0);
        $weights['QB'] = 1; // some other position must carry the round's only weight

        $service = new AutoDraftService(
            $this->config($weights), // K's weight is 0 — would never be rolled by selectPlayer()
            $this->rosterWithCounts(['K' => 99]), // and already "over" any sane limit
            $this->draftPicksOnRound(1),
            $priorityLists,
        );

        self::assertSame(400, $service->selectPlayerAtPosition('K'));
    }

    public function testSelectPlayerAtPositionThrowsWhenListExhausted(): void
    {
        $priorityLists = $this->createMock(PriorityListRepository::class);
        $priorityLists->method('firstAvailable')->willReturn(null);

        $service = new AutoDraftService(
            $this->config(array_fill_keys(Position::codes(), 1)),
            $this->rosterWithCounts([]),
            $this->draftPicksOnRound(1),
            $priorityLists,
        );

        $this->expectException(PlayerNotFoundException::class);
        $service->selectPlayerAtPosition('K');
    }
}
