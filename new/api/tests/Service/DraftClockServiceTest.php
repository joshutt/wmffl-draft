<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ClockStopRepository;
use App\Repository\DraftPickRepository;
use App\Service\DraftClockService;
use App\Service\DraftStateService;
use PHPUnit\Framework\TestCase;

/**
 * Pure logic tests for the port of clock.class.php + DraftUtils.php's
 * getTeamOnClock()/adjustClock() — every dependency is mocked, no DB needed.
 */
final class DraftClockServiceTest extends TestCase
{
    public function testGetTeamOnClockReturnsNullWhenDraftNotStarted(): void
    {
        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('isDraftStarted')->willReturn(false);

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->expects($this->never())->method('findOpenPick');

        $service = new DraftClockService($draftPicks, $this->createMock(ClockStopRepository::class), $draftState);

        self::assertNull($service->getTeamOnClock(2026));
    }

    public function testGetTeamOnClockReturnsNullWhenNoOpenPickLeft(): void
    {
        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('isDraftStarted')->willReturn(true);

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('findOpenPick')->with(2026)->willReturn(null);

        $service = new DraftClockService($draftPicks, $this->createMock(ClockStopRepository::class), $draftState);

        self::assertNull($service->getTeamOnClock(2026));
    }

    public function testGetTeamOnClockReturnsTeamFromOpenPick(): void
    {
        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('isDraftStarted')->willReturn(true);

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('findOpenPick')->with(2026)->willReturn(['round' => 3, 'pick' => 4, 'teamId' => 7]);

        $service = new DraftClockService($draftPicks, $this->createMock(ClockStopRepository::class), $draftState);

        self::assertSame(7, $service->getTeamOnClock(2026));
    }

    public function testGetTotalTimeUsedSkipsExtraTimeLookupForFirstPick(): void
    {
        $prev = time() - 100;

        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('getFullStartTimestamp')->willReturn($prev);

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('maxPickTimestamp')->willReturn(null);

        $clockStops = $this->createMock(ClockStopRepository::class);
        $clockStops->expects($this->never())->method('sumExtraSeconds');

        $service = new DraftClockService($draftPicks, $clockStops, $draftState);

        $before = time();
        $used = $service->getTotalTimeUsed(2026, 1, 1);
        $after = time();

        // used = now - prev - extra(forced 0 for round 1 / pick 1)
        self::assertGreaterThanOrEqual($before - $prev, $used);
        self::assertLessThanOrEqual($after - $prev, $used);
    }

    public function testGetTotalTimeUsedSubtractsExtraPausedTime(): void
    {
        $prev = time() - 500;

        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('getFullStartTimestamp')->willReturn($prev);

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('maxPickTimestamp')->willReturn(null);

        $clockStops = $this->createMock(ClockStopRepository::class);
        $clockStops->expects($this->once())
            ->method('sumExtraSeconds')
            ->with(2026, 2, 3)
            ->willReturn(50);

        $service = new DraftClockService($draftPicks, $clockStops, $draftState);

        $before = time();
        $used = $service->getTotalTimeUsed(2026, 2, 3);
        $after = time();

        // used = now - prev - extra
        self::assertGreaterThanOrEqual($before - $prev - 50, $used);
        self::assertLessThanOrEqual($after - $prev - 50, $used);
    }

    public function testAdjustClockClampsDeductionToZeroWhenTimeUsedExceedsRemaining(): void
    {
        // adjustClock always does two things: deduct from the picking team,
        // then credit whichever team is now on the clock — both are
        // asserted together since they happen in the same call.
        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('isDraftStarted')->willReturn(true);
        $draftState->method('getMaxTimeSeconds')->willReturn(180);
        $draftState->method('getAddTimeSeconds')->willReturn(30);
        $draftState->method('getTeamRemainingSeconds')->willReturnMap([
            [1, 100],
            [2, 170],
        ]);
        $set = [];
        $draftState->expects($this->exactly(2))
            ->method('setTeamRemainingSeconds')
            ->willReturnCallback(function (int $teamId, int $seconds) use (&$set): void {
                $set[$teamId] = $seconds;
            });

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('findOpenPick')->willReturn(['round' => 1, 'pick' => 2, 'teamId' => 2]);

        $service = new DraftClockService($draftPicks, $this->createMock(ClockStopRepository::class), $draftState);

        // Team 1: 100 remaining - 150 used = -50 -> clamped to 0.
        // Team 2 (now on the clock): 170 + 30 add = 200 -> clamped to 180.
        $service->adjustClock(2026, 1, 150);

        self::assertSame([1 => 0, 2 => 180], $set);
    }

    public function testAdjustClockCreditsNextTeamClampedToMaxTime(): void
    {
        $draftState = $this->createMock(DraftStateService::class);
        $draftState->method('isDraftStarted')->willReturn(true);
        $draftState->method('getMaxTimeSeconds')->willReturn(180);
        $draftState->method('getAddTimeSeconds')->willReturn(30);
        $draftState->method('getTeamRemainingSeconds')->willReturnMap([
            [1, 100],
            [2, 170],
        ]);
        $set = [];
        $draftState->expects($this->exactly(2))
            ->method('setTeamRemainingSeconds')
            ->willReturnCallback(function (int $teamId, int $seconds) use (&$set): void {
                $set[$teamId] = $seconds;
            });

        $draftPicks = $this->createMock(DraftPickRepository::class);
        $draftPicks->method('findOpenPick')->willReturn(['round' => 1, 'pick' => 2, 'teamId' => 2]);

        $service = new DraftClockService($draftPicks, $this->createMock(ClockStopRepository::class), $draftState);

        // Team 1: 100 - 20 used = 80. Team 2: 170 + 30 add = 200 -> clamped to 180.
        $service->adjustClock(2026, 1, 20);

        self::assertSame([1 => 80, 2 => 180], $set);
    }
}
