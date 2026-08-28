<?php

declare(strict_types=1);

namespace App\Config;

use App\Domain\Position;
use RuntimeException;

/**
 * Loads and validates config/autodraft.json — the per-round position weight
 * grid and per-position allocation limits that drive AutoDraftService (see
 * docs/auto-draft-spec.md §5). Mirrors the shape of Db::loadConfig(): read,
 * validate loudly, throw RuntimeException with an actionable message.
 * Db::loadConfig() is intentionally NOT refactored onto a shared loader —
 * one is a flat INI section, this is a 2D grid, and JSON represents that
 * grid far better than INI would.
 */
final class AutoDraftConfig
{
    /**
     * @param array<string,int> $allocations pos => allocation limit
     * @param array<int,array<string,int>> $weights round => (pos => weight)
     */
    private function __construct(
        private readonly array $allocations,
        private readonly array $weights,
    ) {
    }

    /**
     * Reads config/autodraft.json off disk and validates it against
     * $maxRound — the highest round number in the season's draftpicks
     * (DraftPickRepository::maxRound()). Every round from 1 to $maxRound
     * must be present and fully specified; there is no fallback to a
     * previous round or a silent default.
     */
    public static function load(int $maxRound): self
    {
        $path = __DIR__ . '/../../config/autodraft.json';

        if (!is_file($path)) {
            throw new RuntimeException(
                "Missing {$path} — see docs/auto-draft-spec.md §5 for the expected shape."
            );
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Could not parse {$path} as a JSON object.");
        }

        return self::fromArray($decoded, $maxRound, $path);
    }

    /**
     * Pure validation over already-decoded config data, split out from
     * load() so tests can exercise malformed configs without touching disk.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data, int $maxRound, string $source = 'autodraft config'): self
    {
        $positions = Position::codes();

        if (!isset($data['allocations']) || !is_array($data['allocations'])) {
            throw new RuntimeException("{$source}: missing 'allocations'.");
        }
        if (!isset($data['weights']) || !is_array($data['weights'])) {
            throw new RuntimeException("{$source}: missing 'weights'.");
        }

        $allocations = [];
        foreach ($positions as $pos) {
            $limit = $data['allocations'][$pos] ?? null;
            if (!is_int($limit) || $limit < 1) {
                throw new RuntimeException("{$source}: allocations.{$pos} must be an integer >= 1.");
            }
            $allocations[$pos] = $limit;
        }

        $weights = [];
        for ($round = 1; $round <= $maxRound; $round++) {
            $row = $data['weights'][$round] ?? null;
            if (!is_array($row)) {
                throw new RuntimeException("{$source}: weights is missing round {$round}.");
            }

            $roundWeights = [];
            $sum = 0;
            foreach ($positions as $pos) {
                $weight = $row[$pos] ?? null;
                if (!is_int($weight) || $weight < 0) {
                    throw new RuntimeException("{$source}: weights.{$round}.{$pos} must be an integer >= 0.");
                }
                $roundWeights[$pos] = $weight;
                $sum += $weight;
            }

            if ($sum === 0) {
                throw new RuntimeException("{$source}: weights.{$round} has every position at weight 0.");
            }

            $weights[$round] = $roundWeights;
        }

        return new self($allocations, $weights);
    }

    /** @return array<string,int> pos => allocation limit */
    public function allocations(): array
    {
        return $this->allocations;
    }

    /** @return array<string,int> pos => weight, for every one of the nine positions */
    public function weightsForRound(int $round): array
    {
        if (!isset($this->weights[$round])) {
            throw new RuntimeException("autodraft config has no weight row for round {$round}.");
        }

        return $this->weights[$round];
    }
}
