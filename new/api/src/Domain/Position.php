<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The nine draftable positions — the single server-side source of truth
 * used by AutoDraftConfig validation and the priority-list CSV parser (see
 * docs/auto-draft-spec.md §2/§12). `players.pos` also has `HC` (head coach),
 * but a head coach is never auto-drafted, so it's deliberately excluded.
 *
 * The frontend keeps its own duplicate `POSITIONS` arrays in
 * CommishPage.tsx and MyPickPanel.tsx — left alone per the spec's §12.
 */
enum Position: string
{
    case QB = 'QB';
    case RB = 'RB';
    case WR = 'WR';
    case TE = 'TE';
    case K = 'K';
    case OL = 'OL';
    case DL = 'DL';
    case LB = 'LB';
    case DB = 'DB';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
