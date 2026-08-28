<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Position;
use App\Http\Guard;
use App\Http\Request;
use App\Http\Response;
use App\Repository\PriorityListRepository;

/**
 * Auto-draft priority-list console endpoints — new for
 * docs/auto-draft-spec.md §7, following CommishController's conventions:
 * every route is behind Guard::requireCommish(), validation is inline and
 * manual.
 */
final class AutoDraftController
{
    public function __construct(
        private readonly PriorityListRepository $priorityLists = new PriorityListRepository(),
    ) {
    }

    /**
     * POST /api/commish/autodraft/priority — multipart CSV upload. Replaces
     * the priority list for every position present in the file; other
     * positions' lists are left untouched.
     */
    public function upload(): void
    {
        Guard::requireCommish();

        $file = Request::file('file');
        if ($file === null) {
            Response::error('A CSV file is required', 400);
        }

        $rows = $this->parseCsv($file['tmpName']);
        if ($rows === []) {
            Response::error('The uploaded CSV had no data rows', 400);
        }

        Response::json($this->priorityLists->replace($rows));
    }

    /**
     * GET /api/commish/autodraft/priority — current list size per position
     * plus every pending row, so the commish can spot unmatched names
     * before draft day.
     */
    public function show(): void
    {
        Guard::requireCommish();

        $this->priorityLists->resolvePending();

        Response::json([
            'counts' => $this->priorityLists->countsByPosition(),
            'pending' => $this->priorityLists->pendingRows(),
        ]);
    }

    /**
     * Parses the uploaded CSV per docs/auto-draft-spec.md §6: header
     * `pos,rank,firstname,lastname`, `rank` optional (defaults to row order
     * within the position when absent or blank).
     *
     * @return list<array{pos:string,rank:int,firstName:?string,lastName:string}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            Response::error('Could not read the uploaded file', 400);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            Response::error('The uploaded CSV was empty', 400);
        }

        $columns = [];
        foreach ($header as $i => $name) {
            $columns[strtolower(trim((string) $name))] = $i;
        }
        if (!isset($columns['pos'], $columns['lastname'])) {
            fclose($handle);
            Response::error("The CSV header must include 'pos' and 'lastname' columns", 400);
        }

        $validPositions = Position::codes();
        /** @var array<string, list<array{firstName:?string,lastName:string}>> $byPosition in file order per position */
        $byPosition = [];

        while (($fields = fgetcsv($handle)) !== false) {
            if (count($fields) === 1 && trim((string) $fields[0]) === '') {
                continue; // blank line
            }

            $pos = strtoupper(trim((string) ($fields[$columns['pos']] ?? '')));
            $lastName = trim((string) ($fields[$columns['lastname']] ?? ''));
            $firstName = isset($columns['firstname']) ? trim((string) ($fields[$columns['firstname']] ?? '')) : '';
            $rankRaw = isset($columns['rank']) ? trim((string) ($fields[$columns['rank']] ?? '')) : '';

            if (!in_array($pos, $validPositions, true)) {
                fclose($handle);
                Response::error("Unknown position '{$pos}' in the uploaded CSV", 400);
            }
            if ($lastName === '') {
                fclose($handle);
                Response::error("A row for position {$pos} is missing lastname", 400);
            }

            $byPosition[$pos][] = [
                'firstName' => $firstName !== '' ? $firstName : null,
                'lastName' => $lastName,
                'rankRaw' => $rankRaw,
            ];
        }
        fclose($handle);

        $rows = [];
        $seenRanks = [];
        foreach ($byPosition as $pos => $posRows) {
            foreach ($posRows as $i => $r) {
                $rank = $r['rankRaw'] !== '' ? (int) $r['rankRaw'] : $i + 1;

                $key = "{$pos}#{$rank}";
                if (isset($seenRanks[$key])) {
                    Response::error("Duplicate rank {$rank} for position {$pos} in the uploaded CSV", 400);
                }
                $seenRanks[$key] = true;

                $rows[] = ['pos' => $pos, 'rank' => $rank, 'firstName' => $r['firstName'], 'lastName' => $r['lastName']];
            }
        }

        return $rows;
    }
}
