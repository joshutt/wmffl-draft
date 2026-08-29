# WMFFL Draft App — Voice Announcing Spec & Plan

Status: implemented 2026-08-28 on branch `voice-announce`. Ships disabled;
see §8 for what still has to be done per-host before it can make a sound.
Prototype: `src/voice.html` (standalone HTML, hits the legacy-shaped
`services/picks` endpoint — kept for reference, not shipped).

## 1. What this is

A third SPA route, `/announcer`, meant to be opened in its own window and
screen-shared (with audio) into the draft-day video call. It shows the pick
status line and the draft board like the owners' board does, and **speaks
each pick aloud** as it lands, via Amazon Polly.

It is not the owners' board and not the commish console — but the commish
console gains a small panel that controls it remotely: announcements on/off,
which Polly voice to use, and the round/pick to (re)start announcing from
after a page reload.

Scope note: draft day is **2026-08-29**, the day after this spec. The feature
is therefore built to be **off by default** (`draft.voice.enabled` defaults to
`false`) and to fail soft — a broken or misconfigured announcer must never
affect the owners' board, the clock, or pick submission. Nothing in this plan
touches `PickService`, `DraftClockService`, or any existing endpoint contract.

## 2. Decisions (from interview, 2026-08-28)

| Question | Decision |
| --- | --- |
| Who can open `/announcer` and read its settings | **Commish only** — `Guard::requireCommish()` on the settings endpoints, same page gate as `CommishPage`. The screen-share machine logs in as commish. |
| How the AWS SDK loads | **CDN `aws-sdk-2.x.min.js`, injected on demand** when the announcer page mounts. No new npm deps; the board and commish pages don't pay for it. |
| Reload / resume behavior | **Commish field + local memory** — the DB holds the floor; the page also remembers its own last-announced pick in `localStorage` and resumes from whichever is later. Changing the commish field discards the local pointer. |
| Page layout | **Stripped presentation view** — no app header/nav/login chrome. Big status line over the full board, plus a small audio-control strip. |

## 3. Where configuration lives

Two different places, on purpose:

**`new/api/config/db.ini` → `[Voice_Values]`** — infrastructure, hand-provisioned
per environment, gitignored (same convention as `[DB_Values]`). Already present
in `db.ini.default`, `db.ini.test`, and `db.ini.real`:

```ini
[Voice_Values]
pool_id=us-east-1:xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
region=us-east-1
```

These are the Cognito **identity pool** used for unauthenticated Polly access
from the browser, and the AWS region. They change per environment and must
never be in git — hence the ini file, not the DB.

**The `config` table → `draft.voice.*`** — operator settings the commish flips
during the draft, so they're shared across machines and survive a reload:

| Key | Type | Default | Meaning |
| --- | --- | --- | --- |
| `draft.voice.enabled` | `'true'`/`'false'` | `false` | Announce at all |
| `draft.voice.voiceId` | string | `Matthew` | Polly voice, whitelisted |
| `draft.voice.startRound` | int ≥ 1 | `1` | Floor: first round to announce |
| `draft.voice.startPick` | int ≥ 1 | `1` | Floor: first pick in that round |

Per `DraftStateService`'s existing contract ("nothing else in the app builds
`draft.*` key strings by hand"), these keys get typed accessors on that
service rather than a new service reaching into `ConfigRepository`.

## 4. Backend changes (`new/api`)

### 4.1 `src/Config/VoiceConfig.php` (new)

Mirrors `AutoDraftConfig`: read, validate loudly, throw `RuntimeException`
with an actionable message. Reads the `[Voice_Values]` section of the same
`config/db.ini` that `Db` reads.

```php
final class VoiceConfig
{
    public static function load(): self;     // throws RuntimeException
    public function poolId(): string;
    public function region(): string;
}
```

`Db::loadConfig()` is deliberately **not** refactored onto a shared loader —
same reasoning `AutoDraftConfig` already documents: they validate different
things and one bad refactor here breaks DB connectivity for the whole app.

The controller catches the exception rather than letting it 500, so a host
with no `[Voice_Values]` still serves the settings endpoint and the announcer
page can show "voice not configured" instead of a blank error.

### 4.2 `src/Domain/PollyVoice.php` (new)

Server-side whitelist of allowed voices, so nothing arbitrary is ever sent to
Polly (`Text` is server-derived, `VoiceId` would otherwise be client-chosen).
Enum in the shape of `Domain/Position.php`:

`Joanna, Matthew, Ivy, Justin, Kendra, Kimberly, Salli, Joey` — the
prototype's eight, all valid Polly **neural**-engine voices.

### 4.3 `src/Service/DraftStateService.php` (edit)

Four key constants + accessors, following the file's existing style:

```php
public function getVoiceSettings(): array;   // {enabled, voiceId, startRound, startPick}
public function setVoiceEnabled(bool $enabled): void;
public function setVoiceId(string $voiceId): void;
public function setVoiceStart(int $round, int $pick): void;
```

`getVoiceSettings()` applies the defaults from §3's table when a key is absent,
so a database that has never seen this feature behaves as "off, round 1 pick 1".

### 4.4 `src/Http/Controllers/VoiceController.php` (new)

Both routes behind `Guard::requireCommish()`, registered in
`public/index.php` alongside the other `/commish/*` routes.

```
GET  /api/commish/voice
POST /api/commish/voice   {enabled?, voiceId?, startRound?, startPick?}
```

Both return the same body:

```json
{
  "enabled": false,
  "voiceId": "Matthew",
  "startRound": 1,
  "startPick": 1,
  "poolId": "us-east-1:…",
  "region": "us-east-1",
  "voices": ["Joanna", "Matthew", "…"],
  "configError": null
}
```

`configError` is the `VoiceConfig` failure message when `[Voice_Values]` is
missing or blank (and `poolId`/`region` are then `null`). POST is a partial
update — only the keys present in the body are written. Validation:
`enabled` must be a bool; `voiceId` must be a `PollyVoice` case; `startRound`
and `startPick` must be integers ≥ 1. Anything else → `400`.

No new tables, no schema change.

## 5. Frontend changes (`new/web`)

The board data the announcer needs is **already** in `GET /api/draft/board`:
`picks[]` carries `round, pick, teamName, playerName, playerPos, playerTeam`,
and `currentPick`/`onDeckPick`/`lastPick` give the status line. No board
endpoint change is needed, and the announcer reuses the single `useBoard()`
poll that `Shell` already runs — it does **not** add a second board poll.

### 5.1 `src/announce.ts` (new)

Pure, no React — easy to eyeball and to reason about:

- `POSITION_NAMES` (`QB` → `Quarterback`, … plus `HC` → `Head Coach`) and
  `NFL_TEAM_NAMES` (32 abbreviations → full names, incl. both `JAX`/`JAC`),
  lifted from the prototype.
- `pickKey(round, pick): number` → `round * 1000 + pick`, the single ordering
  scalar used everywhere below.
- `pickAnnouncement(pick)` → `"The <franchise> select: <player>, <Position>,
  from the <NFL team>."` The trailing clause is dropped when `playerTeam` is
  null or the position is `OL` (matching the prototype — an offensive line
  "player" in this league has no single NFL team).
- `onClockAnnouncement(current, onDeck)` → `"The <team> are on the clock. The
  <onDeck> are on deck."`
- `endOfRoundAnnouncement(round)` → `"End of Round <n>."`
- `planPickAnnouncements(picks, last)` → which picks still need reading out,
  per §5.2. Pure, and separate from the hook so the logic that decides whether
  a reload re-reads the draft aloud is testable without a browser — see §7.
- `planOnClockAnnouncement(board, floor, onClockKey)` → who to announce as on
  the clock. Deliberately a **separate** call, made at a different moment —
  see §5.2's ordering rule.

### 5.2 `src/useAnnouncer.ts` (new)

Takes `(board, settings, audioReady)` and owns all announcer state.

**Floor / resume logic.** Per §2's decision:

- `floorKey = pickKey(settings.startRound, settings.startPick)` — the first
  pick that may ever be spoken.
- `localStorage` holds one JSON entry per season,
  `wmffl.voice.pointer.<season>` = `{"floor":…,"last":…}` — the key of the last
  pick actually accounted for, stamped with the `floorKey` it was recorded
  against. (One entry rather than the two separate keys originally specced, so
  the pair can't be left half-written.) Every read and write is wrapped in
  try/catch: private-mode browsers throw, and losing the pointer degrades to
  the commish's floor, which is exactly what that field is for.
- If the stored floor ≠ the current `floorKey`, the commish changed the field
  → discard the local pointer and start at `floorKey`. Otherwise start at
  `max(floorKey, storedLast + 1)`. This is what makes a plain reload silent
  instead of re-reading 40 picks aloud.
- The entry is also stamped with `board.draftStartedAt`, and discarded when
  that changes — see below.

**Start Draft resets the announcer.** Pressing Start Draft re-stamps
`draft.full.start` (`CommishService::startDraft()`), which the board already
exposes as `draftStartedAt`. The pointer records the value it was written
under, and `storedPointerApplies()` rejects it when the two differ, so the
announcer starts over from the commish's floor.

This has to go through the server: `localStorage` belongs to the announcer's
browser, which is normally *not* the machine the commish is clicking on, so
the commish page cannot clear it directly. Keying off a value already in the
board payload means no new endpoint, no new config key, and it works however
many announcer windows are open.

Two consequences worth knowing:

- At a genuine draft start there are no picks yet (or only preselect
  auto-picks, which *should* be read out), so the reset is exactly right.
- Pressing Start Draft **mid-draft** will re-announce every pick already made,
  because the floor goes back to the commish's start round/pick. That button
  already resets every team clock, so it's a deliberate, confirm-gated act —
  and the confirm text now says announcing restarts too. The escape hatch is
  the same one as always: set "start at round/pick" to the current slot first.
- An entry written before this stamp existed has no `startedAt`, and is
  treated as still applying, so shipping this mid-draft doesn't trigger a
  replay on the first load.

**One owner for the pointer.** `takeNewPickLines(board)` is the only thing
that advances the pointer, and it returns the lines for exactly the picks it
advanced past — so the two steps can't come apart. This matters more than it
looks: an earlier version advanced the pointer in the poll effect and *then*
checked whether announcing was switched on, so any pick landing in that gap
was marked handled while its lines were thrown away. It went silent on picks
while still calling out who was on the clock. The one deliberate discard is
the "announcements off / audio not unlocked" path below.

**Undo rolls the pointer back.** The commish's Undo Pick reopens the last
slot, and the re-pick reuses the same round/pick numbers — which the pointer
would otherwise read as already handled, silencing every pick from then on.
`takeNewPickLines` clamps the pointer down to the highest filled pick on each
pass (never below the floor) and persists that immediately, since the undo
itself announces nothing.

**Ordering rule: the on-the-clock line is decided last, and separately.**
Reading out a run of picks takes many seconds — longer than the 5s board
poll — so more picks routinely land mid-run. Planning the on-the-clock line
alongside the picks, from one snapshot, made it stale before it was spoken:
in testing the announcer named a team that had *already* picked and then
immediately read out that same team's selection.

So the drain loop works like this, and the order is the point:

1. Say every pick the board we already have reports as new.
2. Finding none, **re-fetch the board** and look again. If that turns up
   picks, say them and go back to 1.
3. Only when a *fresh* board has nothing new, announce who's on the clock —
   evaluated against that fresh board, not the one the run started from.

Step 2 re-fetches rather than waiting for the next poll, so the announcer
rolls straight from one pick into the next instead of pausing mid-catch-up.
`planOnClockAnnouncement` is keyed on the slot, so it is announced once per
slot, not once per catch-up lap. The loop reads the board itself rather than
being handed a queue by the poll effect — that's what keeps a single owner
for the pointer (see above).

**When announcements are off** (`enabled === false`, or audio not yet
unlocked), the pointer still advances silently and nothing is enqueued —
so flipping the toggle on mid-draft speaks the *next* pick, never a backlog.

**Speech pipeline.** A serial queue: shift → synthesize → decode → play →
`onended` → next. Every failure branch (credentials error, Polly error, decode
error, empty stream) must clear the "speaking" flag and continue the queue —
the prototype has a fix comment about exactly this stall, and this
implementation uses promises so there is one `finally`, not four call sites.

```js
AWS.config.region = region
AWS.config.credentials = new AWS.CognitoIdentityCredentials({ IdentityPoolId: poolId })
new AWS.Polly({ apiVersion: '2016-06-10' })
  .synthesizeSpeech({ Text, OutputFormat: 'mp3', VoiceId, Engine: 'neural' })
```

The credentials object is created once and reused (Cognito refreshes it), not
rebuilt per utterance as the prototype does.

**SDK loading.** `aws-sdk-2.x.min.js` from `sdk.amazonaws.com` is injected as
a `<script>` on first use and awaited via a cached promise, so it is fetched
once and only on this route. Pin the exact version rather than a floating one.

**Audio unlock.** Browsers refuse to start an `AudioContext` outside a user
gesture, so the page cannot speak until someone clicks. The unlock button
creates + resumes the context and plays the prototype's 440 Hz beep, which
doubles as the operator's confirmation that audio is actually routed into the
call before the draft starts.

### 5.3 `src/pages/AnnouncerPage.tsx` (new)

- Same three-state commish gate as `CommishPage`: loading → login card →
  "Commissioner access required" → console.
- Polls `GET /api/commish/voice` every 5s so commish changes take effect
  without a reload.
- Renders, top to bottom:
  - a large status line — Round, Pick, countdown, on the clock, on deck, last
    pick — sized to read when projected into a call;
  - the full draft board (reuses `PicksGrid`, under a `presentation` class for
    larger type);
  - a compact control strip: "Enable Announcements" button (or an
    audio-is-live indicator), current voice, on/off state, the text of the last
    thing spoken, and any error (`configError`, Polly failure, SDK load
    failure). Errors are visible but never block the board from rendering.

### 5.4 `src/App.tsx` (edit)

`Shell` branches on `/announcer`: that route renders **only**
`<AnnouncerPage boardState={boardState} />` — no `app-header`, no `ClockBar`
(the page has its own, bigger one). An "Announcer" nav link appears next to
"Commish" for commish sessions. `router.tsx` needs no change; production
`.htaccess` already falls back to `index.html`, so the deep link works.

### 5.5 `src/api.ts` (edit)

`VoiceSettings` interface + `api.voiceSettings()` / `api.saveVoiceSettings(patch)`.

### 5.6 `src/pages/CommishPage.tsx` (edit)

One new card in `commish-left`, using the existing `run()` helper so it gets
the same busy/message handling as every other control:

- **Announcements** checkbox → `enabled`
- **Voice** select, populated from the response's `voices`
- **Start at** Round / Pick number inputs (saved on blur, like the hangout URL
  field) — the "we had to reload the announcer" escape hatch
- **Open Announcer ↗** link to `/announcer` in a new window
- a muted status line: region + "pool configured", or the `configError`

### 5.7 `src/index.css` (edit)

An `/* Announcer */` section — presentation-sized status line and board rows,
in the existing white/maroon/gold palette.

## 6. What gets announced

| Trigger | Utterance |
| --- | --- |
| A pick lands | "The Dallas Cowboys select: Josh Allen, Quarterback, from the Buffalo Bills." |
| Pick has no NFL team, or is `OL` | "…select: Joe Blow, Offensive Line." |
| Last pick of a round | "End of Round 3." |
| Clock moves to a new team | "The New York Giants are on the clock. The Eagles are on deck." |

Nothing is announced at all until the commish presses Start Draft
(`board.draftStarted`). Team 1 counts as "on the clock" for the whole time
everyone is sitting around beforehand, so announcing it then is just noise —
and it would be stale by the time the draft actually began. The slot is left
unmarked while waiting, so the line still plays the instant the draft starts,
after any preselect auto-picks that fire with it.

Announcement latency is bounded by the existing 5s board poll — acceptable,
and the same bound the owners' board already lives with.

## 7. Testing

- `tests/Config/VoiceConfigTest.php` — parses a temp ini; errors on missing
  section, missing key, empty value. Modeled on `AutoDraftConfigTest`.
- `tests/Service/VoiceSettingsTest.php` (`DbTestCase`) — defaults with no rows
  present, set/get round trip, partial update leaves other keys alone.
- `tests/integration.php` — extend the parity pass: commish GET/POST
  `/api/commish/voice`, a rejected bad `voiceId` (400), and a 403 for an
  owner-level session.
- `new/web/src/announce.check.mjs`, run by `npm run check:announce` — covers
  the announcement wording (including the OL / no-NFL-team cases and unknown
  codes); the pick-planning cases that matter (catch-up across several picks,
  end-of-round, a reload with a restored pointer announcing nothing, a
  pointerless reload re-reading everything, a commish-moved floor); and a
  undo-and-re-pick (the pointer rolls back and the re-pick is announced); and
  a simulation of the drain loop with picks landing mid-run, which pins the
  §5.2 ordering rule: during a burst an on-the-clock line is never followed by
  a pick, it is said exactly once, it names the team holding the first
  still-open pick, and picks are still announced after announcements are
  switched on mid-draft without replaying the backlog.
  No test framework is added: Node strips the TypeScript types on import, so
  it's a dependency-free script that runs even on the 9p mount.
- The audio pipeline in `useAnnouncer.ts` is not covered — it needs a browser,
  a user gesture and a live Polly call. That stays a manual pre-draft check
  against the seeded local DB (`php new/api/bin/seed-draft.php`, then the
  dev-server from `new/web/README.md`): open `/announcer`, click Enable
  Announcements, confirm the beep, then make a pick and listen.

## 8. Deployment / operational prerequisites

1. **`db.ini` is hand-provisioned and gitignored on every host** — the server's
   copy must gain a `[Voice_Values]` section before deploy or the panel shows
   `configError`. Add this to `docs/deployment-spec.md`'s prereq list.
2. **AWS side** — the Cognito identity pool must allow unauthenticated
   identities, and its unauth IAM role must grant `polly:SynthesizeSpeech`
   (that alone; nothing broader). Verify with the announcer page's test beep
   plus one real announcement *before* draft day.
3. **Network** — the announcing machine must reach `sdk.amazonaws.com` and
   `polly.<region>.amazonaws.com`.
4. **Rebuild `dist/`** with `new/web/build.sh` and commit it — the host has no
   Node and deploys are a plain `git pull`.
5. **Audio routing** — in the video call, share the announcer window *with*
   system/tab audio, and confirm with the test beep. This is the single most
   likely draft-day failure and it is not something the app can detect.
6. **Cost** — ~100 characters per announcement, ~2 utterances per pick; a
   full draft is a few tens of thousands of characters, i.e. cents at neural
   Polly pricing. Not a concern, but the pool is unauthenticated, which is why
   the settings endpoint is commish-gated rather than public.

## 9. Risks

- **Timeline.** This lands the day before the draft. Mitigation: the feature
  ships disabled (`draft.voice.enabled` default `false`), lives on its own
  route, and adds no code to the pick or clock paths. Worst case on draft day
  is "nobody turns it on".
- **Autoplay policy.** Nothing speaks until a human clicks Enable. The page
  makes that state loud rather than failing silently.
- **Duplicate/backlog announcements after a reload.** Handled by §5.2's
  floor + localStorage pointer; the commish field is the manual override when
  that isn't enough.
- **`src/voice.html` is a legacy-shaped prototype** — it polls `services/picks`
  and expects picks nested by round. The real board endpoint is flat and named
  differently. The prototype is a reference for the *announcement text, voice
  list, and Polly call*, not code to port verbatim.

## 10. Build order

1. ✅ `VoiceConfig` + `PollyVoice` + `DraftStateService` accessors, with unit tests.
2. ✅ `VoiceController` + routes; verified with curl as anonymous, owner and commish.
3. ✅ `announce.ts` (pure text + planning helpers) + `announce.check.mjs`.
4. ✅ `api.ts` types + commish console panel.
5. ✅ `useAnnouncer` + `AnnouncerPage` + CSS + `App.tsx` route.
6. ✅ `integration.php` additions (76 checks pass); `dist/` rebuilt.
7. ⬜ **Remaining, and not doable from a dev checkout**: provision
   `[Voice_Values]` on the server's `db.ini`, confirm the Cognito pool's
   unauth role grants `polly:SynthesizeSpeech`, and do the manual
   listen-to-it pass in §7 — including sharing the window with audio into an
   actual call. Until that's done the feature is untested against real Polly.
