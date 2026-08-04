# WMFFL Draft App Modernization — Spec & Plan

Status: draft for review. Reflects decisions made in interview on 2026-08-04, updated with Phase 0 findings.

## 1. Decisions (from interview)

| Question | Decision |
|---|---|
| Draft date | **August 29, 2026** — 25 days out from spec date. More runway than the initial "within 2 weeks" estimate; timeline in §9 reflects the real date. |
| Scope | **Draft tool only** — login, public draft board, pick submission, commish console. Not the broader league app (trades/waivers/stats/schedule/etc. stay on the legacy codebase). |
| Backend | **Modern PHP**, JSON REST API. Drop `DB_DataObject`; replace with PDO + prepared statements. |
| Frontend | **React SPA**, built to static assets, served from the same webroot as today. |
| Migration | **Big-bang rewrite**, built in parallel, single cutover before draft day. |
| Real-time | **Keep polling** (no WebSockets). React SPA polls new REST endpoints on the same cadence as today. |
| Hosting | **Shared host, SSH access confirmed, PHP 8.3.31.** Composer can run directly on the host. Deploys via **git pull on the server**; Node is *not* available on the host, so the React build output (`web/dist/`) is committed to the repo rather than built server-side (see §3). New app lives in the same webroot, API under `/api/`. Staging DB exists for testing. All Phase 0 questions from §7 are resolved. |
| DB schema | **Unchanged.** New code reads/writes the existing tables (`draftpicks`, `roster`, `config`, `team`/`teamnames`, `user`, `owners`, `draftPickHold`, `draftclockstop`, `newplayers`, `nflrosters`, `weekmap`). |
| Chat | **Out of scope.** `chat.php`/`postMessage.php` are not wired into the current page anyway; owners keep using the Google Hangout link. |
| Auth | **Keep MD5 compatibility** (no forced password resets, no schema change). Fix the plumbing: parameterized queries, a real `/api/auth/login` endpoint, proper session handling. Hash algorithm upgrade is explicitly deferred to a follow-up. |
| Commish console | **In scope.** The draft can't run without it. |
| Risk posture | **Go/no-go checkpoint** ~3–4 days before draft day. If the rewrite isn't stable by then, fall back to the legacy app for this season and finish the rewrite for next season. |

## 2. Goals / Non-goals

**Goals**
- Replace the server-rendered jQuery pages with a React SPA for the public draft board and the commish console.
- Replace `DB_DataObject` and raw string-interpolated SQL with a small PDO-based data access layer using prepared statements everywhere.
- Preserve all current draft-day functionality (see §4 parity checklist) — this is a re-platform, not a feature redesign.
- Fix the one correctness bug that actually matters for a live multi-user event: the pick-submission race condition (see §6).

**Non-goals**
- Rebuilding chat, rosters-outside-draft, trades, waivers, stats, schedule, gameplan, or any other page under the broader league app.
- WebSockets / push updates.
- Database schema changes.
- Password hashing upgrade.
- Full automated test coverage (see §8 — targeted, not comprehensive, given the timeline).

## 3. Target architecture

```
src/                      (legacy app — left running, untouched, as fallback)
new/
  api/                     PHP JSON API (PSR-4 autoloaded via Composer)
    public/index.php       front controller (single entry point)
    src/Http/              route table + request/response handling
    src/Repository/        PDO repositories: DraftPicks, Roster, Config, Team, User, Player, ClockStop
    src/Service/           DraftClockService, PickService, AuthService (ports of clock.class.php / DraftUtils.php logic)
    src/Db.php             PDO connection wrapper (replaces global $conn)
  web/                     React SPA source (Vite)
    src/
    dist/                  build output — deployed into the shared-host webroot
```

Deployment shape on the shared host: the React build (`web/dist/`) lives at the site root; the PHP API lives under `/api/` in that same webroot behind the front controller; `.htaccess` handles SPA routing (fallback to `index.html`) and routes `/api/*` to the PHP front controller.

### Why not a framework (Laravel/Slim/etc.)

The plan defaults to a **hand-rolled front controller with an explicit route table** (a few dozen lines) rather than adopting a framework: the API surface is small (~15 endpoints, §5) and a framework wouldn't reduce the amount of code actually needed here, even with Composer/SSH access confirmed available. Composer is still used, just for PSR-4 autoloading of `new/api/src`, not a framework. Target language level is **PHP 8.3** — readonly properties, enums, `match`, constructor property promotion, and named arguments are all fair game in the new code.

### Deploying the React build (git-pull deploy, no Node on host)

Deploys are `git pull` on the server, and **Node is not available on the host**, so the build (`npm run build`) has to happen locally, and `web/dist/` (the build output) is committed to the repo so a plain `git pull` is sufficient to deploy it — unusual to commit build artifacts, but it matches this project's existing no-CI deploy model without introducing one under time pressure. This makes "did you rebuild `dist/` before this push" a manual step that's easy to forget — the deploy checklist in §9's cutover step must include "run `npm run build` and confirm `web/dist/` is part of the commit" explicitly, since a stale `dist/` would silently serve the previous version of the SPA after a deploy.

The existing `.gitignore` pattern of keeping environment-specific files (`.htaccess`, `*.ini`, `conf/wmffl.conf`) untracked and pre-provisioned on the server carries over: the new API's DB credentials file follows the same convention — never committed, already in place on the server before the first `git pull` of the new code.

### Data access layer

One repository class per table actually needed by the draft tool (7 tables, not the ~60 in `src/lib/DataObjects`). Each repository:
- Wraps PDO with prepared statements — no string-interpolated SQL.
- Exposes methods named for the operation, not the table (e.g. `DraftPickRepository::findOpenPick($season)`, not a generic ORM `find()`).
- The `config` key-value table (`draft.start`, `draft.clock.run`, `draft.team.<id>`, etc.) is wrapped by a `DraftStateService`, so route handlers never build `config` key strings directly — that string-building is one of the more error-prone patterns in the legacy code (`clockService.php`'s `explode('.', $key)` parsing, `stopClock.php`'s bespoke key names) and centralizing it removes a class of typos.

Port, don't redesign, the logic already in `DraftUtils.php` and `clock.class.php` (`getTeamOnClock`, `getCurrentPick`, `getTotalTimeUsed`, `adjustClock`, `checkPreselect`) into `Service` classes — this logic is correct today and re-deriving it from scratch under time pressure is the wrong place to take risk.

## 4. Feature parity checklist

Every row must work in the new app before cutover.

**Public draft board**
- [ ] Login (username/password, sets session) / logout
- [ ] Session check on load ("am I logged in, which team am I") — replaces `checkIn.php`
- [ ] Presence heartbeat — replaces `stillHere.php`
- [ ] Live picks grid: round/pick/franchise/selection, polled — replaces `picks.php`
- [ ] Clock display: current round/pick, team on the clock, time remaining, running/paused state — replaces `clockService.php`
- [ ] Last pick / on-deck team display
- [ ] Per-team clock list (all teams' remaining time)
- [ ] Roster viewer per team — replaces `rosterBlock.php`/`rosterHtml.php`
- [ ] Available-player list with position + NFL team filter — replaces `playerList.php`
- [ ] Submit a pick — replaces `setPick.php`, calls `PickService`
- [ ] Queue/hold a preselection when not on the clock, and clear it — replaces `saveTeamPick`/`clearSelection.php`
- [ ] Auto-advance through consecutive teams' preselections — port of `checkPreselect`

**Commish console**
- [ ] Commish-only route guard (equivalent of today's `usernum == 2` / `$_SESSION['commish']` checks, done properly via a role check in the API, not scattered per-page)
- [ ] Login/presence table (who's checked in, green/red, last-seen) — replaces commish `index.php` query
- [ ] Start the draft — replaces `startDraft.php`
- [ ] Start/stop the clock — replaces `stopClock.php`
- [ ] Force an auto-pick for an absent team — replaces `commish/autopick.php`
- [ ] Undo the last pick — replaces `undopick.php`

## 5. API surface (draft REST endpoints)

All JSON, all behind session auth except `/api/auth/login`.

| Method | Path | Replaces | Notes |
|---|---|---|---|
| POST | `/api/auth/login` | `loginA.php` | Parameterized query, MD5 compat preserved |
| POST | `/api/auth/logout` | `logout.php` | |
| GET | `/api/session` | `checkIn.php` | |
| POST | `/api/session/heartbeat` | `stillHere.php` | |
| GET | `/api/draft/board` | `picks.php` + `clockService.php` merged | One call instead of two separate polls — real reduction in round trips vs. legacy |
| POST | `/api/draft/pick` | `setPick.php` | Wrapped in a transaction; see §6 |
| POST | `/api/draft/hold` | `saveTeamPick` (in `DraftUtils.php`) | |
| DELETE | `/api/draft/hold` | `clearSelection.php` | |
| GET | `/api/players` | `playerList.php` | Query params: `pos`, `nfl` |
| GET | `/api/roster/{teamId}` | `rosterBlock.php`/`rosterHtml.php` | |
| GET | `/api/commish/status` | commish `index.php` query | Commish-only |
| POST | `/api/commish/draft/start` | `startDraft.php` | Commish-only |
| POST | `/api/commish/clock/start` \| `/stop` | `stopClock.php` | Commish-only |
| POST | `/api/commish/pick/auto` | `commish/autopick.php` | Commish-only |
| POST | `/api/commish/pick/undo` | `undopick.php` | Commish-only |

## 6. Correctness fix included in scope: pick-submission race

Today's `setPick.php` → `DraftUtils.php:makePick()` checks player availability, then issues separate `UPDATE`/`INSERT` statements with no transaction or locking. Two owners submitting the same player within the same poll window can both pass the availability check before either write lands — this is a real risk during a live draft with a fast clock, not a hypothetical.

The rewrite fixes this as part of `PickService::makePick()`, at low cost since it's a rewrite anyway: wrap the read-check + writes in a single DB transaction, re-validate availability inside the transaction (`SELECT ... FOR UPDATE` on the open `draftpicks` row), and return a clean `409 Conflict` JSON error if another pick landed first, so the SPA can refresh and show the player is gone instead of silently corrupting state.

## 7. Phase 0 findings (resolved)

All blocking questions are answered — implementation can proceed.

1. **Composer/SSH access** — confirmed available; Composer runs directly on the host.
2. **PHP version** — **8.3.31**. Target language level for all new code; readonly properties, enums, `match`, constructor property promotion, and named arguments are all safe to use.
3. **Deploy mechanism** — `git pull` on the server, not FTP/SFTP.
4. **Node/npm on host** — **not available**. `web/dist/` (React build output) is committed to the repo and deployed via the same `git pull`; see §3's note on why this makes "rebuild before pushing" a manual checklist item, not an automated one.
5. **Path layout** — new app lives in the **same webroot** as today, API under `/api/`. No subdomain or separate path.
6. **Staging environment** — **confirmed to exist**. Integration testing (§8) and the concurrent-pick race test (§6) run against it, not production.

## 8. Testing approach

No test suite exists today; given the timeline, testing effort is targeted at the highest-risk logic rather than comprehensive coverage:

- **PHPUnit, targeted**: `DraftClockService` (time math — this is the most subtle and error-prone logic in the legacy app) and `PickService` (the transactional race-condition fix in §6). Not a full suite across every endpoint.
- **Manual smoke-test checklist**, run against staging before the go/no-go checkpoint and again after final deploy: full parity checklist from §4, exercised by at least 2 simulated "owners" picking concurrently to verify the race fix, plus the commish start/stop/undo/auto-pick flows.
- No automated frontend tests planned given the timeline — manual verification only.

## 9. Timeline (draft day: Saturday, August 29, 2026 — 25 days from spec date)

| Dates | Work |
|---|---|
| Aug 4–5 | **Done.** Phase 0 complete (§7); scaffold `new/api` (Composer, PSR-4) and `new/web` (Vite), confirm staging DB connectivity |
| Aug 6–9 | **Done.** Data access layer (repositories + `Db.php`), auth endpoint, `DraftClockService`/`PickService` ports (incl. §6 transactional race fix), `/api/draft/board`, `/api/draft/pick`, `/api/draft/hold`; PHPUnit coverage for `DraftClockService` and `PickService` (incl. a real concurrent-process test of the race fix) |
| Aug 9–13 | React SPA scaffold (Vite), public draft board (picks grid, clock, roster viewer, player list + filters), wired to polling |
| Aug 13–16 | Pick submission + hold/preselect UI, auto-advance behavior |
| Aug 16–19 | Commish console (status table, start draft, start/stop clock, undo, force auto-pick) |
| Aug 19–22 | Integration pass on staging: full parity checklist, concurrent-pick test, commish flows |
| Aug 22–23 | Bug-fixing buffer |
| **Aug 24–25 (draft day − 4/5)** | **Go/no-go checkpoint.** Stable → proceed to deploy rehearsal. Not stable → fall back to legacy app for this season, continue rewrite for next season. |
| Aug 25–27 | Deploy rehearsal: `npm run build`, commit `web/dist/`, `git pull` on the server, verify `/api/` routing and SPA fallback via `.htaccess` in the real webroot; remaining bug fixing |
| Aug 28 | Final cutover: rebuild `web/dist/` from latest source, confirm it's in the commit being deployed (see §3), `git pull` on production, smoke test |
| **Aug 29** | **Draft day.** Legacy app left in place, untouched, as standby rollback. |

This has more slack than the original 2-week estimate — the extra days are intentionally left as buffer (Aug 22–23, Aug 25–27) rather than pulled forward, since a rewrite of a live multi-user event is exactly the kind of project where "finished early" is worth more than "gold-plated."

## 10. Rollback plan

The legacy `src/` app is left running, untouched, and pointed at the same database throughout — it is not deleted or modified during this project. Because the DB schema is unchanged, rollback at any point (including mid-draft) is: repoint the webroot/DNS back to `src/`, no data migration needed. This is the safety net the go/no-go checkpoint and the "don't touch the legacy app" rule both exist to preserve — don't undermine it by editing `src/` files during the rewrite.
