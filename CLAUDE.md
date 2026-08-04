# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A legacy PHP web app that runs a live, real-time fantasy football draft for the WMFFL league. Owners log in, see a shared draft board and countdown clock, and pick players in turn; a commissioner ("commish") console runs the draft (start/stop the clock, force auto-picks, undo picks). There is no build system, no test suite, and no framework — it's a flat collection of PHP scripts served directly by a web server, polled from the browser with jQuery.

## Running / developing

There are no build, lint, or test commands — this is plain PHP with no package scripts (`composer.json` is empty; PEAR's `DB_DataObject` is expected to be available on the include path, not vendored).

To stand it up locally:
1. Copy `conf/wmffl.conf.default` to `conf/wmffl.conf` and fill in `[DB_Values]` (db name/user/password) and `[Paths]`.
2. Copy `src/lib/dataobjects.ini.default` to `src/lib/dataobjects.ini` and fill in the `DB_DataObject` DSN (`mysql://user:pass@localhost/dbname`) plus the schema/class locations — these must point at `src/lib/DataObjects`.
3. Serve the `src/` directory with PHP (e.g. `php -S localhost:8000 -t src`) against a MySQL database matching the schema implied by the DataObjects classes (`draftpicks`, `roster`, `config`, `team`/`teamnames`, `user`, `owners`, `draftPickHold`, `draftclockstop`, etc.).
4. `src/commish/` is the commissioner-only console; `src/` root (`index.php`/`draft.php`) is the public draft board.

There is no local mock of the DB layer — anything beyond reading static files effectively requires a real MySQL instance wired up as above.

## Architecture

**Everything is state kept in MySQL, polled by the browser.** There's no websocket/push layer. `src/update.jquery.js` sets `setInterval` timers that hit small JSON-emitting PHP endpoints (`picks.php`, `clockService.php`, `checkIn.php`, `stillHere.php`) every 1–15s and patches the DOM. Any change to draft state (a pick, a clock start/stop) only becomes visible to other clients on their next poll — there is no server push.

**Request bootstrap chain**: nearly every PHP entry point starts with `require_once "utils/start.php"`, which itself requires `utils/setup.php`. Understand this chain before touching any page:
- `utils/setup.php` sets the include path, points `error_log` at `logs/draft.log`, parses `conf/wmffl.conf`, configures PEAR `DB_DataObject`'s static options from it, and — notably — **splats every `$_REQUEST` key into a same-named global variable** (`foreach ($_REQUEST as $key => $val) { $$key = $val; }`). This means request params like `?team=`, `?player=`, `?autoDraft=` show up as bare PHP variables (`$team`, `$player`, `$autoDraft`) throughout the codebase, not via `$_REQUEST[...]` — grep for the bare variable name when tracing where a param is used.
- `utils/start.php` opens the session, connects via `mysqli_connect`, forces `America/New_York`, and computes/caches the current season/week into `$_SESSION` (`currentSeason`, `currentWeek`, `weekName`) by querying the `weekmap` table against `now()`. It also copies every `$_SESSION` value into globals the same way. Login state lives in `$_SESSION['isin']`, `$_SESSION['teamnum']`/`$_SESSION['usernum']`, and `$_SESSION['commish']`.
- There are two near-duplicate copies of the utils/DataObjects support code: `src/utils/` + `src/lib/DataObjects/` (used by the public site) and `src/commish/utils/` (used by the commish console). They aren't shared/symlinked — check both when changing shared logic like `StringUtils.php`.

**Data access is a mix of two styles in the same files**: PEAR `DB_DataObject` model classes in `src/lib/DataObjects/*.php` (one per table, thin subclasses of `DB_DataObject` with public properties matching columns, auto-generated between the `###START_AUTOCODE`/`###END_AUTOCODE` markers) alongside raw `mysqli_query()` calls built with **directly interpolated variables** (not parameterized) against the global `$conn`. When editing, match whichever style the surrounding file already uses; don't silently introduce a third pattern. Because queries are interpolated rather than parameterized, be deliberate about not introducing new injectable input from `$_REQUEST`/globals.

**Draft/clock state lives in a generic key-value `config` table**, not dedicated columns — e.g. `draft.start`, `draft.clock.run`, `draft.clock.start`, `draft.full.start`, `draft.clock.maxTime`, `draft.clock.addTime`, `draft.team.<teamid>` (that team's remaining clock seconds), `draft.login.<userid>` (last-seen heartbeat timestamp), `draft.hangout.url`. `src/clock.class.php` and `src/DraftUtils.php` contain the core clock/pick math (`getCurrentPick`, `getTotalTimeUsed`, `adjustClock`, `getTeamOnClock`) — read both together before changing draft-clock behavior, since the elapsed-time calculation depends on `draftclockstop` rows (manual pauses) layered on top of the `config` timestamps.

**The pick flow**: `setPick.php` validates the submitted player (`id-<playerid>` format), then `DraftUtils.php:makePick()` does: validate availability (`confirmPlayerAvailable`) → update `draftpicks.playerid` → insert into `roster` → clear `draftPickHold` for that team/player → `adjustClock()` (deduct time from the picking team, add increment to the next team on the clock). `draftPickHold` holds a team's queued pre-selection so `DraftUtils.php:checkPreselect()` can auto-advance through consecutive teams that pre-picked while off the clock. `src/commish/autopick.php` is the commish-forced version of this for absent owners.

**Auth is minimal and non-standard**: no hashing library — passwords are compared with MySQL `MD5()` in raw SQL. Commish/admin gating is done ad hoc per-page via `$usernum == 2` or `$_SESSION['commish']` checks, not a shared authorization layer — when adding a privileged endpoint, follow the existing per-file pattern rather than inventing a new one. Note `src/login.php` currently starts with `print "Hi"; exit();` before any real logic and has a stray unmatched `)` further down — it is effectively dead/broken code, not the real login path in use (check `src/loginA.php` and how the front end actually calls in before assuming `login.php` is live).

**Front end** is server-rendered PHP HTML (`draft.php`/`index.php` are near-duplicates — the public draft board) plus jQuery polling (`update.jquery.js`, `commish/commish.js`) and Bootstrap/MDB via CDN — there is no bundler, no npm, no component framework.
