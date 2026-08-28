<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AutoDraftConfig;
use App\Exception\PlayerNotFoundException;
use App\Repository\DraftPickRepository;
use App\Repository\PriorityListRepository;
use App\Repository\RosterRepository;
use Closure;

/**
 * The configuration-driven auto-draft algorithm — replaces
 * CommishService::positionNeeds() + PlayerRepository::findBestAvailableByScore()
 * per docs/auto-draft-spec.md §4. Two entry points: selectPlayer() runs the
 * full weighted-position algorithm; selectPlayerAtPosition() is the
 * commish's explicit per-row override, ignoring weights/allocations
 * entirely.
 */
final class AutoDraftService
{
    /** @var Closure(int):int given $max, returns a random int in [0, $max] */
    private readonly Closure $randomInt;

    /**
     * @param Closure(int):int|null $randomInt injectable in place of
     *     random_int(0, $max), so the weighted pick is deterministic under test
     */
    public function __construct(
        private readonly AutoDraftConfig $config,
        private readonly RosterRepository $roster,
        private readonly DraftPickRepository $draftPicks,
        private readonly PriorityListRepository $priorityLists,
        ?Closure $randomInt = null,
    ) {
        $this->randomInt = $randomInt ?? static fn (int $max): int => random_int(0, $max);
    }

    /**
     * The full algorithm from docs/auto-draft-spec.md §4: pick a position by
     * round weight (proportional random, respecting allocation limits),
     * take the first available player on that position's priority list, and
     * re-roll or bump allocation limits as needed until a pick is made or
     * every list is exhausted.
     *
     * @throws PlayerNotFoundException
     */
    public function selectPlayer(int $season, int $teamId): int
    {
        $round = $this->draftPicks->minOpenRoundForTeam($teamId, $season);
        if ($round === null) {
            throw new PlayerNotFoundException("Team {$teamId} has no open picks left in season {$season}");
        }

        $this->priorityLists->resolvePending();

        $weights = $this->config->weightsForRound($round);
        $allocations = $this->config->allocations();
        $counts = $this->roster->countActiveByPosition($teamId);
        $maxAllocation = max($allocations);

        $exhausted = [];
        $bump = 0;

        while (true) {
            [$eligible, $zeroedByAllocation] = $this->eligiblePositions($weights, $allocations, $counts, $exhausted, $bump);

            if ($eligible === []) {
                if ($bump > $maxAllocation) {
                    throw new PlayerNotFoundException(
                        "No available player for team {$teamId}'s auto-pick in round {$round} —"
                        . ' every eligible position\'s priority list is exhausted.'
                    );
                }

                $bump++;
                continue;
            }

            $pos = $this->weightedPick($eligible);
            $playerId = $this->priorityLists->firstAvailable($pos);

            if ($playerId !== null) {
                $this->log($teamId, $round, $weights, $zeroedByAllocation, $exhausted, $bump, $pos, $playerId);

                return $playerId;
            }

            // That position's list is exhausted — re-roll among the rest,
            // per docs/auto-draft-spec.md §4 step 8.
            $exhausted[] = $pos;
        }
    }

    /**
     * The commish's explicit per-row override — first available player on
     * $pos's priority list, ignoring weights and allocations entirely.
     *
     * @throws PlayerNotFoundException
     */
    public function selectPlayerAtPosition(string $pos): int
    {
        $this->priorityLists->resolvePending();

        $playerId = $this->priorityLists->firstAvailable($pos);
        if ($playerId === null) {
            throw new PlayerNotFoundException("No available player on the {$pos} priority list");
        }

        error_log(sprintf('AUTO-PICK explicit pos=%s player=%d', $pos, $playerId));

        return $playerId;
    }

    /**
     * Splits $weights into positions still eligible to be rolled (weight >
     * 0, under its allocation limit + $bump, and not in $exhausted) versus
     * the ones zeroed out purely by the allocation check this round — the
     * latter is kept only for the audit log.
     *
     * @param array<string,int> $weights
     * @param array<string,int> $allocations
     * @param array<string,int> $counts
     * @param list<string> $exhausted
     * @return array{0:array<string,int>, 1:list<string>}
     */
    private function eligiblePositions(array $weights, array $allocations, array $counts, array $exhausted, int $bump): array
    {
        $eligible = [];
        $zeroedByAllocation = [];

        foreach ($weights as $pos => $weight) {
            if ($weight <= 0 || in_array($pos, $exhausted, true)) {
                continue;
            }

            $limit = $allocations[$pos] + $bump;
            $have = $counts[$pos] ?? 0;
            if ($have >= $limit) {
                $zeroedByAllocation[] = $pos;
                continue;
            }

            $eligible[$pos] = $weight;
        }

        return [$eligible, $zeroedByAllocation];
    }

    /**
     * Strictly proportional weighted random pick: P(pos) = weight / sum(weights).
     *
     * @param array<string,int> $eligible non-empty, values > 0
     */
    private function weightedPick(array $eligible): string
    {
        $sum = array_sum($eligible);
        $roll = ($this->randomInt)($sum - 1);

        $cursor = 0;
        foreach ($eligible as $pos => $weight) {
            $cursor += $weight;
            if ($roll < $cursor) {
                return $pos;
            }
        }

        // Unreachable given $sum > 0 and $roll in [0, $sum - 1], but keeps
        // the method total in case a bad randomizer closure is injected.
        return array_key_first($eligible);
    }

    /**
     * One line per auto-pick to the existing draft log — docs/auto-draft-spec.md
     * §9. Captures the round's raw weights, positions zeroed by allocation,
     * any positions re-rolled past for an exhausted list, the bump level,
     * the roll outcome, and the chosen player.
     *
     * @param array<string,int> $weights
     * @param list<string> $zeroedByAllocation
     * @param list<string> $exhaustedLists
     */
    private function log(
        int $teamId,
        int $round,
        array $weights,
        array $zeroedByAllocation,
        array $exhaustedLists,
        int $bump,
        string $pickedPos,
        int $playerId,
    ): void {
        error_log(sprintf(
            'AUTO-PICK team=%d round=%d weights=%s zeroedByAllocation=%s exhaustedLists=%s bump=%d picked=%s player=%d',
            $teamId,
            $round,
            json_encode($weights),
            json_encode($zeroedByAllocation),
            json_encode($exhaustedLists),
            $bump,
            $pickedPos,
            $playerId,
        ));
    }
}
