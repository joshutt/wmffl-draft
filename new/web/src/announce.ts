import type { BoardPick } from './api'

// The words the announcer says. Pure functions and lookup tables only — no
// React, no AWS — so the phrasing can be read and corrected without going
// near the audio pipeline (docs/voice-announce-spec.md §5.1). Ported from the
// `src/voice.html` prototype, which is where this wording was tuned by ear.

/** Position code → what Polly should actually pronounce. */
export const POSITION_NAMES: Record<string, string> = {
  QB: 'Quarterback',
  RB: 'Running back',
  WR: 'Wide Receiver',
  TE: 'Tight End',
  K: 'Kicker',
  OL: 'Offensive Line',
  DL: 'Defensive Line',
  LB: 'Linebacker',
  DB: 'Defensive Back',
  HC: 'Head Coach',
}

/** NFL abbreviation → full team name, so Polly doesn't spell out "GB". */
export const NFL_TEAM_NAMES: Record<string, string> = {
  // NFC East
  DAL: 'Dallas Cowboys',
  NYG: 'New York Giants',
  PHI: 'Philadelphia Eagles',
  WAS: 'Washington Commanders',
  // NFC North
  CHI: 'Chicago Bears',
  DET: 'Detroit Lions',
  GB: 'Green Bay Packers',
  MIN: 'Minnesota Vikings',
  // NFC South
  ATL: 'Atlanta Falcons',
  CAR: 'Carolina Panthers',
  NO: 'New Orleans Saints',
  TB: 'Tampa Bay Buccaneers',
  // NFC West
  ARI: 'Arizona Cardinals',
  LAR: 'Los Angeles Rams',
  SF: 'San Francisco 49ers',
  SEA: 'Seattle Seahawks',
  // AFC East
  BUF: 'Buffalo Bills',
  MIA: 'Miami Dolphins',
  NE: 'New England Patriots',
  NYJ: 'New York Jets',
  // AFC North
  BAL: 'Baltimore Ravens',
  CIN: 'Cincinnati Bengals',
  CLE: 'Cleveland Browns',
  PIT: 'Pittsburgh Steelers',
  // AFC South
  HOU: 'Houston Texans',
  IND: 'Indianapolis Colts',
  JAX: 'Jacksonville Jaguars',
  JAC: 'Jacksonville Jaguars',
  TEN: 'Tennessee Titans',
  // AFC West
  DEN: 'Denver Broncos',
  KC: 'Kansas City Chiefs',
  LV: 'Las Vegas Raiders',
  LAC: 'Los Angeles Chargers',
}

/**
 * A single scalar that orders every slot in the draft, used for all the
 * "have we announced this yet" comparisons. 1000 picks per round is far more
 * headroom than a 12-team league needs, and keeps the number readable in
 * localStorage while debugging.
 */
export function pickKey(round: number, pick: number): number {
  return round * 1000 + pick
}

/** "The Foo select: Josh Allen, Quarterback, from the Buffalo Bills." */
export function pickAnnouncement(pick: BoardPick): string {
  const position = pick.playerPos !== null ? (POSITION_NAMES[pick.playerPos] ?? pick.playerPos) : null
  const head = `The ${pick.teamName} select: ${pick.playerName}`
  const withPosition = position !== null ? `${head}, ${position}` : head

  // An offensive line "player" in this league isn't a single NFL player, so
  // it has no one NFL team to name — same special case the prototype made.
  if (pick.playerTeam === null || pick.playerPos === 'OL') {
    return `${withPosition}.`
  }

  const team = NFL_TEAM_NAMES[pick.playerTeam] ?? pick.playerTeam

  return `${withPosition}, from the ${team}.`
}

/** "The Foo are on the clock. The Bar are on deck." */
export function onClockAnnouncement(
  current: BoardPick | null,
  onDeck: BoardPick | null,
): string | null {
  if (current === null) {
    return null
  }

  const head = `The ${current.teamName} are on the clock.`

  return onDeck !== null ? `${head} The ${onDeck.teamName} are on deck.` : head
}

export function endOfRoundAnnouncement(round: number): string {
  return `End of Round ${round}.`
}

export interface StoredPointer {
  floor: number
  last: number
  /** board.draftStartedAt when this was written; absent in pre-stamp entries */
  startedAt?: number | null
}

/**
 * Whether a pointer remembered by this browser still applies.
 *
 * Two things invalidate it, and both are deliberate commish actions:
 *
 * - the commish moved the "start at round/pick" floor, which is the explicit
 *   "announce from here instead" override;
 * - the commish pressed **Start Draft**, which re-stamps `draft.full.start`.
 *   That's a new draft, so whatever this browser remembered about the old one
 *   is meaningless. Going through the server this way is what makes it work
 *   at all — localStorage lives in the announcer's browser, which is usually
 *   not the machine the commish is clicking on.
 *
 * An entry written before the stamp existed adopts the current draft rather
 * than forcing a replay the first time this ships mid-draft.
 */
export function storedPointerApplies(
  stored: StoredPointer | null,
  floor: number,
  startedAt: number | null,
): boolean {
  if (stored === null || stored.floor !== floor) {
    return false
  }

  return stored.startedAt === undefined || stored.startedAt === startedAt
}

/**
 * The pickKey of the last filled slot on the board, or `null` if none are.
 *
 * Used to notice the board going *backwards*: the commish's "Undo Pick"
 * reopens the most recent slot, and the re-pick that follows reuses the same
 * round/pick numbers. Without rolling the pointer back to match, that re-pick
 * looks like something already handled and is never announced — the announcer
 * goes quiet on picks while still calling out who's on the clock.
 */
export function highestFilledPickKey(picks: BoardPick[]): number | null {
  let highest: number | null = null

  for (const p of picks) {
    if (p.playerName === null) {
      continue
    }

    const key = pickKey(p.round, p.pick)
    if (highest === null || key > highest) {
      highest = key
    }
  }

  return highest
}

export interface PickPlan {
  /** What to say, in draft order */
  lines: string[]
  /** pickKey of the last pick now accounted for */
  last: number
}

/**
 * Which picks still need reading out, given where the announcer left off.
 *
 * Pure and separate from the audio pipeline on purpose: this is the logic
 * that decides whether a reload re-reads the whole draft aloud, so it's the
 * part that most needs to be testable in isolation.
 *
 * `last` is the pickKey of the most recent pick already accounted for —
 * `floor - 1` when starting fresh at the commish's configured floor. Callers
 * feed the returned `last` back in next time.
 */
export function planPickAnnouncements(picks: BoardPick[], last: number): PickPlan {
  const lines: string[] = []
  let nextLast = last

  // Highest pick number in each round, so the last one can be followed by
  // "End of Round N" — derived from the board rather than assumed, since
  // round length is whatever draftpicks says it is.
  const lastPickOfRound = new Map<number, number>()
  for (const p of picks) {
    lastPickOfRound.set(p.round, Math.max(lastPickOfRound.get(p.round) ?? 0, p.pick))
  }

  for (const p of picks) {
    if (p.playerName === null) {
      continue
    }

    const key = pickKey(p.round, p.pick)
    if (key <= nextLast) {
      continue
    }

    lines.push(pickAnnouncement(p))
    if (lastPickOfRound.get(p.round) === p.pick) {
      lines.push(endOfRoundAnnouncement(p.round))
    }
    nextLast = key
  }

  return { lines, last: nextLast }
}

export interface OnClockPlan {
  line: string | null
  /** pickKey of the slot whose on-the-clock line has now been emitted */
  onClockKey: number | null
}

/**
 * Who to announce as on the clock — deliberately NOT part of
 * planPickAnnouncements().
 *
 * These two decisions have to be made at different moments. Reading out a
 * run of picks takes many seconds, and more picks land while it's happening;
 * an on-the-clock line planned alongside the picks is stale by the time it's
 * spoken, which is how the announcer ended up naming a team that had already
 * picked and then immediately reading that team's pick. So the caller says
 * everything it knows about first, re-checks for new picks, and only calls
 * this once there is genuinely nothing left in the queue.
 *
 * Gated on the floor too: if the draft hasn't reached the commish's start
 * slot yet, the announcer stays quiet about who's up.
 */
export function planOnClockAnnouncement(
  board: { draftStarted: boolean; currentPick: BoardPick | null; onDeckPick: BoardPick | null },
  floor: number,
  onClockKey: number | null,
): OnClockPlan {
  // Before the commish presses Start Draft, team 1 is nominally "on the
  // clock" for as long as everyone is sitting around waiting — announcing it
  // then is just noise, and it would be stale by the time the draft actually
  // begins. `onClockKey` is deliberately left untouched so the line still
  // plays the moment the draft starts.
  if (!board.draftStarted) {
    return { line: null, onClockKey }
  }

  const current = board.currentPick
  const currentKey = current !== null ? pickKey(current.round, current.pick) : null

  if (currentKey === null || currentKey < floor || currentKey === onClockKey) {
    return { line: null, onClockKey }
  }

  return { line: onClockAnnouncement(current, board.onDeckPick), onClockKey: currentKey }
}
