# Auto-Draft Redesign — Spec & Plan

Status: draft for review. Decisions from interview on 2026-08-27. Draft day is **2026-08-29**.

## 1. Why

Auto-draft today is a direct port of the legacy `src/commish/autopick.php`:
`CommishService::autoPick()` → `positionNeeds()` → `PlayerRepository::findBestAvailableByScore()`.
It picks a position set from a hardcoded starter/backup heuristic with round gates baked into a PHP
`match`, then takes the highest scorer at that position by last season's points, weeks 1–14.

Three problems:

| Problem | Detail |
|---|---|
| Strategy is hardcoded | Tuning who gets drafted when requires a code change and a deploy. The round gates (`no K before round 13`, `no OL before round 10`) are literals inside a `match` expression. |
| Rookies are invisible | `findBestAvailableByScore()` does `JOIN playerscores`, not `LEFT JOIN`. Any player with no scoring row for last season can never be auto-drafted. |
| Output is predictable | Same team, same slot, same player every time (modulo a `RAND()` tiebreak that only fires on exact point ties). |

The replacement is configuration-driven and deliberately randomized: a **per-round position weight
grid** and **per-position allocation limits** from a committed config file, plus **per-position
priority lists** uploaded as CSV before draft day.

**This governs auto-draft only.** Nothing here constrains a human owner picking through the normal
UI — an owner may draft any available player at any position, in any quantity, at any time.

## 2. Decisions (from interview)

| Question | Decision |
|---|---|
| Weight math | **Strictly proportional.** `P(pos) = weight / sum(remaining weights)`. Weight `0` means never picked. |
| All positions blocked | **Raise every allocation limit by 1** and re-evaluate. Repeat until something opens. |
| Chosen position's list exhausted | **Re-roll** — set that position's weight to 0 and pick again among the rest. |
| Positional minimums | **No.** Maximums only. "Must get a K" is expressed as a heavy K weight in a late round. |
| Priority list delivery | **CSV upload in the commish console**, real multipart upload. |
| Player matching | **By name + position.** Unmatched rows are **stored as pending** so they resolve if the player is added to `players` later. |
| Weights / allocations storage | **Committed JSON config file** in the repo. |
| Missing config rounds | **Require a full config.** Validate loudly at load; no silent defaults, no "reuse last round". |
| Config scope | **One global config** for all teams, all drafts. |
| Roster counting | **Active roster rows** — `roster.DateOff IS NULL`, the team's whole current roster including prior-year holds. |
| Availability filter | **Undrafted only.** If a player is on the priority list they are draftable — no `nflrosters` join, no `playerscores` requirement. |
| Position set | **All nine draftable positions**: `QB, RB, WR, TE, K, OL, DL, LB, DB`. (`HC` is never auto-drafted.) |
| Old logic | **Full replacement.** `positionNeeds()` and `findBestAvailableByScore()` are deleted. |
| Audit trail | **Log line per auto-pick** to the existing draft log. No schema change, no UI. |
| Trigger | **Unchanged** — the commish `AUTO` button. No clock-expiry auto-fire. |

## 3. Goals / Non-goals

**Goals**
- Make auto-draft strategy tunable without a code change.
- Make rookies and any other player draftable by auto-draft, by sourcing candidates from an
  explicit list rather than from last season's scoring table.
- Produce varied but shaped rosters — position choice is random, weighted by round.
- Give the commish a pre-draft view of which uploaded names failed to match a player.

**Non-goals**
- Automatic firing on clock expiry. The clock is display-only in the browser today; adding a
  server-side trigger is a separate concern from the picking algorithm.
- Per-team or per-season configuration.
- Positional minimums.
- Constraining human picks in any way.
- A migration system, a shared config loader, or retargeting the frontend's duplicated position
  lists. All out of scope.

## 4. The algorithm

On an auto-pick for a team:

1. **Round** — `DraftPickRepository::minOpenRoundForTeam($teamId, $season)` (existing). Null means
   the team has no picks left → error.
2. **Weights** — the config's row for that round: an integer for each of the nine positions.
3. **Counts** — `RosterRepository::countActiveByPosition($teamId)` (existing, unchanged). Counts
   every roster row with `DateOff IS NULL`.
4. **Zero out** any position where `count >= allocation limit`.
5. **If every weight is now zero** → raise *every* allocation limit by 1, re-evaluate from step 4.
   Repeat until at least one position is open.
6. **Weighted random pick** among the non-zero weights, strictly proportional. `random_int()`.
7. Take the **first still-available player** on that position's priority list — lowest rank, not
   currently rostered by anyone.
8. **If that list is exhausted** → set that position's weight to 0 and re-roll from step 6. If every
   eligible position's list is exhausted, bump the limits (step 5) and continue.
9. **Log** the decision, return the player id.

**Termination.** Config validation guarantees every round has at least one position with weight > 0,
so the limit-bump loop always opens something. Bumping is capped: once the bump exceeds the largest
configured allocation, every position is eligible. If all nine lists are exhausted at that point,
throw `PlayerNotFoundException`.

### Worked example

Round 3. Config row: `QB 1, RB 8, WR 6, TE 3, K 0, OL 2, DL 1, LB 1, DB 1`.
Team already has 5 RB (limit 5) and 2 TE (limit 2).

- RB and TE zero out on the allocation check; K was already 0.
- Remaining: `QB 1, WR 6, OL 2, DL 1, LB 1, DB 1` — sum 12.
- WR is picked with probability 6/12, OL 2/12, each of QB/DL/LB/DB 1/12.
- Say the roll lands on OL. The OL priority list is walked from rank 1; the first name not on any
  active roster is the pick.
- If every OL on the list is already drafted, OL drops to 0 and the roll repeats over the remaining
  `QB 1, WR 6, DL 1, LB 1, DB 1` — sum 10.

## 5. Configuration

`new/api/config/autodraft.json` — **committed to the repo.**

JSON rather than following the `db.ini` convention because the root `.gitignore` excludes `*.ini`,
which would make the file untracked and force a manual server-side edit for every weight change.
This file holds no secrets, so it ships with a normal `git pull`. It is also a 2D grid, which INI
represents badly.

```json
{
  "allocations": {
    "QB": 2, "RB": 5, "WR": 5, "TE": 2, "K": 1,
    "OL": 3, "DL": 4, "LB": 4, "DB": 4
  },
  "weights": {
    "1": { "QB": 1, "RB": 8, "WR": 6, "TE": 1, "K": 0, "OL": 0, "DL": 1, "LB": 1, "DB": 1 },
    "2": { "QB": 2, "RB": 7, "WR": 7, "TE": 2, "K": 0, "OL": 1, "DL": 2, "LB": 2, "DB": 2 }
  }
}
```

> Values above are illustrative. Real weights and allocations are set by the commish before draft
> day.

`App\Config\AutoDraftConfig` — `load()`, `weightsForRound(int $round): array<string,int>`,
`allocations(): array<string,int>`. Mirrors the shape of `Db::loadConfig()`: read, validate, throw
`RuntimeException` with an actionable message. `Db::loadConfig()` is **not** refactored onto a shared
loader.

**Validation, at load time, all failures fatal:**
- every round from 1 to `MAX(Round)` in `draftpicks` for the season is present;
- every round row defines all nine positions;
- all values are integers `>= 0`;
- every round has at least one position with weight `> 0`;
- every position has an allocation limit `>= 1`.

## 6. Priority lists

### Schema

The repo has no migration system. DDL goes into `new/api/tests/schema.sql` and is applied by hand to
staging and production — see §10.

```sql
CREATE TABLE autodraft_priority (
  id        INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pos       VARCHAR(2)  NOT NULL,
  rank      INT         NOT NULL,
  playerid  INT         NULL,          -- NULL = pending, unmatched
  firstname VARCHAR(25) NULL,          -- as uploaded, kept for re-matching
  lastname  VARCHAR(25) NOT NULL,
  UNIQUE KEY uq_pos_rank (pos, rank),
  KEY idx_pos_rank (pos, rank)
) ENGINE=InnoDB;
```

`pos` is `VARCHAR(2)` to match `players.pos`. All nine position codes are two characters or fewer.

### CSV format

Header row: `pos,rank,firstname,lastname`.

`rank` is optional — when absent, rank defaults to row order within the position. One file may carry
every position, or one file per position may be uploaded separately. An upload **replaces** the list
for every position present in the file, and leaves other positions untouched.

```csv
pos,rank,firstname,lastname
RB,1,Bijan,Robinson
RB,2,Jahmyr,Gibbs
WR,1,Ja'Marr,Chase
```

### Matching

`firstname` + `lastname` + `pos` against `players`, case-insensitive, trimmed, restricted to
`usePos = 1`.

Rows that match **zero** players, and rows that match **more than one**, are stored with
`playerid = NULL` as *pending*, retaining the uploaded name and a reason. Pending rows are skipped
during selection.

`PriorityListRepository::resolvePending()` re-attempts matching for all pending rows. It runs on
upload and at the start of each auto-pick — the table is small enough that this is free — so a player
added to `players` after the upload resolves automatically with no re-upload.

### Selection query

```sql
SELECT ap.playerid
FROM autodraft_priority ap
LEFT JOIN roster r ON r.PlayerID = ap.playerid AND r.DateOff IS NULL
WHERE ap.pos = ? AND ap.playerid IS NOT NULL AND r.TeamID IS NULL
ORDER BY ap.rank
LIMIT 1
```

Availability is the absence of an active roster row — nothing else. Note `draftpicks.playerid` is a
second, independent record of drafted-ness; auto-draft consults `roster` only, matching the existing
`PlayerRepository::findAvailable()` behavior.

## 7. Upload path

No upload machinery exists anywhere in the repo. Three layers need new plumbing.

- **`new/api/src/Http/Request.php`** — add `Request::file(string $field): ?array` over `$_FILES`,
  validating the upload error code, a size cap, and `is_uploaded_file()`. `Request::json()` reads
  `php://input` and cannot handle multipart.
- **`new/web/src/api.ts`** — `request()` currently sets `'Content-Type': 'application/json'` whenever
  a body is present, which destroys the multipart boundary. Make the header conditional on
  `!(init.body instanceof FormData)`, and add an `uploadPriorityList(file)` method.
- **`new/api/src/Http/Controllers/AutoDraftController.php`** — new, following `CommishController`
  conventions exactly: `final class`, promoted readonly dependencies, a route docblock per method,
  `Guard::requireCommish()` as the first line, inline manual validation, domain exceptions mapped to
  status codes at the controller boundary.

### Endpoints

| Route | Purpose |
|---|---|
| `POST /api/commish/autodraft/priority` | Multipart CSV upload. Returns `{ imported, pending: [{pos, rank, name, reason}], counts: {pos: int} }`. |
| `GET /api/commish/autodraft/priority` | Current list size per position plus all pending rows, so the commish can spot problems before draft day. |

Both registered in `new/api/public/index.php`, both commish-gated.

### UI

`new/web/src/pages/CommishPage.tsx` gains a priority-list panel using the page's existing
`run(label, action)` busy/message helper: a file input, an upload button, and a rendering of import
counts and pending rows.

## 8. Selection service

`App\Service\AutoDraftService` holds the algorithm. Constructor takes `AutoDraftConfig`,
`RosterRepository`, `DraftPickRepository`, `PriorityListRepository`, and an **injectable randomizer**
(`\Closure(int $max): int`, defaulting to `random_int(...)`) so the weighted pick is deterministic
under test.

Public surface:
- `selectPlayer(int $season, int $teamId): int` — the full algorithm in §4.
- `selectPlayerAtPosition(string $pos): int` — first available on that position's list.

`CommishService::autoPick()` is rewired:
- `positionNeeds()` is deleted — and with it the `UnhandledMatchError` foot-gun (adding a position to
  the `foreach` list without a `match` arm is a runtime fatal, not a static error) and the `?? 0`
  round bug (a team with no open picks got round 0, making every gated position eligible);
- `$pos === null` → `AutoDraftService::selectPlayer()`;
- `$pos` given (the commish's per-row dropdown) → `selectPlayerAtPosition()`, ignoring weights and
  allocations; an exhausted list throws `PlayerNotFoundException`;
- the trailing on-clock-vs-hold branch is **unchanged**.

`PlayerRepository::findBestAvailableByScore()` becomes dead and is removed.

### Known pre-existing behavior, preserved

Auto-picking a team that is *not* on the clock writes a preselection hold via
`DraftPickHoldRepository::save()`, which is a `REPLACE INTO` — it silently overwrites any
preselection that owner had queued. This redesign does not change that; it is noted here so it is not
mistaken for new behavior.

## 9. Logging

One `error_log()` line per auto-pick to the existing draft log, capturing: team, round, the raw round
weights, positions zeroed by allocation, the bump level if any, the roll outcome, any positions
re-rolled past for an exhausted list, and the chosen player.

This is the only audit trail — no schema change, no UI surface.

## 10. Deployment

- `new/api/config/autodraft.json` is tracked in git and deploys with a normal `git pull`. No manual
  server provisioning step, unlike `db.ini`.
- `autodraft_priority` **must be created by hand** on staging and production before cutover. The repo
  has no migration system and `tests/schema.sql` is explicitly not a migration. Record the step in
  `docs/deployment-spec.md`.
- Any frontend change requires `new/web/build.sh` locally and **committing `new/web/dist/`** in the
  same commit — Node is not available on the host and deploys are a plain `git pull`, so an
  uncommitted `dist/` silently serves the old SPA.

## 11. Verification

> **Before any DB-touching command in `new/api`: `cp config/db.ini.test config/db.ini`.**
> Mandatory — pointing at real data has caused two data-loss incidents.

**Unit tests** (`vendor/bin/phpunit` from `new/api`), mocked repositories per
`DraftClockServiceTest`'s style:
- weighted pick is proportional — with a stubbed randomizer, walking the roll value across the full
  range hits each position for exactly its weight's share;
- a position at its allocation limit is never chosen;
- all positions blocked → limits bump by 1 and a pick is made;
- an exhausted priority list re-rolls to another position rather than failing;
- all lists exhausted throws `PlayerNotFoundException` and does not hang;
- config validation rejects a missing round, a missing position within a round, a negative weight,
  and an all-zero round.

**Import tests**: a CSV with a clean row, an unknown name, and an ambiguous name yields one matched
row and two pending rows; adding the missing player then running `resolvePending()` promotes it.

**Integration** (`new/api/tests/integration.php`): `php bin/seed-draft.php`, then
`PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 public/index.php`, then upload a priority CSV and
assert an auto-pick returns a player from the expected list.

**Manual**: build with `new/web/build.sh` (npm/vite cannot run directly on the `/mnt/e` 9p mount),
seed, log in as commish, upload a CSV, click `AUTO` on several rows across several rounds, and
confirm from `logs/draft.log` that chosen positions track the configured weights and that allocation
limits hold.

## 12. Files

**New**
- `new/api/config/autodraft.json`
- `new/api/src/Config/AutoDraftConfig.php`
- `new/api/src/Domain/Position.php` — PHP 8.3 enum, the single server-side source of truth for the
  nine positions, used by config validation and CSV parsing. The frontend's duplicate lists in
  `CommishPage.tsx` and `MyPickPanel.tsx` are left alone.
- `new/api/src/Service/AutoDraftService.php`
- `new/api/src/Repository/PriorityListRepository.php`
- `new/api/src/Http/Controllers/AutoDraftController.php`
- `new/api/tests/Service/AutoDraftServiceTest.php`
- `new/api/tests/Config/AutoDraftConfigTest.php`
- `new/api/tests/Service/PriorityListImportTest.php`

**Modified**
- `new/api/src/Service/CommishService.php` — rewire `autoPick()`, delete `positionNeeds()`
- `new/api/src/Repository/PlayerRepository.php` — remove `findBestAvailableByScore()`
- `new/api/src/Http/Request.php` — add `file()`
- `new/api/public/index.php` — register the two routes
- `new/api/tests/schema.sql` — `autodraft_priority` DDL
- `new/api/tests/integration.php` — end-to-end upload + auto-pick
- `new/web/src/api.ts` — FormData content-type fix, `uploadPriorityList()`
- `new/web/src/pages/CommishPage.tsx` — priority-list panel
- `docs/deployment-spec.md` — manual `autodraft_priority` DDL step
