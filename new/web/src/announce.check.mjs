// Checks for the announcer's pure logic — the wording it speaks and, more
// importantly, its decision about what still needs saying
// (docs/voice-announce-spec.md §5.1/§5.2).
//
// Run it with `npm run check:announce`. There is no test framework in this
// project and this deliberately doesn't add one: Node 23+ strips TypeScript
// types on import, so this is a plain script with no dependencies and no
// build step — it can be run straight from the checkout, including on the
// 9p mount where `npm run build` can't run (see build.sh).
//
// It covers announce.ts only. The audio pipeline in useAnnouncer.ts needs a
// browser, a user gesture and a real Polly call, so that stays a manual
// pre-draft check.

import {
  endOfRoundAnnouncement,
  highestFilledPickKey,
  onClockAnnouncement,
  pickAnnouncement,
  pickKey,
  planOnClockAnnouncement,
  planPickAnnouncements,
  storedPointerApplies,
} from './announce.ts'

let pass = 0
let fail = 0

const check = (ok, label, detail) => {
  if (ok) {
    pass++
    console.log(`  ok  ${label}`)
  } else {
    fail++
    console.log(`FAIL  ${label}${detail !== undefined ? ' — ' + JSON.stringify(detail) : ''}`)
  }
}

const pick = (round, n, teamName, playerName, playerPos, playerTeam) => ({
  round,
  pick: n,
  teamId: 1,
  teamName,
  playerId: playerName === null ? null : 1,
  playerName,
  playerPos,
  playerTeam,
  pickTime: null,
})

// ------------------------------------------------------------- wording
console.log('\n== Announcement wording ==')

check(
  pickAnnouncement(pick(1, 1, 'Reapers', 'Josh Allen', 'QB', 'BUF')) ===
    'The Reapers select: Josh Allen, Quarterback, from the Buffalo Bills.',
  'a normal pick names franchise, player, position and NFL team',
  pickAnnouncement(pick(1, 1, 'Reapers', 'Josh Allen', 'QB', 'BUF')),
)
check(
  pickAnnouncement(pick(2, 3, 'Bandits', 'Some Guy', 'OL', 'GB')) ===
    'The Bandits select: Some Guy, Offensive Line.',
  'an OL pick omits the NFL team clause',
  pickAnnouncement(pick(2, 3, 'Bandits', 'Some Guy', 'OL', 'GB')),
)
check(
  pickAnnouncement(pick(2, 4, 'Bandits', 'Free Agent', 'TE', null)) ===
    'The Bandits select: Free Agent, Tight End.',
  'a player with no NFL team omits the clause too',
)
check(
  pickAnnouncement(pick(3, 2, 'Reapers', 'Trevor Lawrence', 'QB', 'JAC')).includes(
    'Jacksonville Jaguars',
  ),
  'both Jacksonville abbreviations resolve',
)
check(
  pickAnnouncement(pick(3, 3, 'Reapers', 'Mystery Man', 'ZZ', 'XXX')) ===
    'The Reapers select: Mystery Man, ZZ, from the XXX.',
  'unknown position/team codes fall through as-is rather than breaking',
)
check(
  onClockAnnouncement(pick(1, 6, 'Wolves', null, null, null), pick(1, 7, 'Ravens', null, null, null)) ===
    'The Wolves are on the clock. The Ravens are on deck.',
  'on-the-clock names the current team and the one on deck',
)
check(
  onClockAnnouncement(pick(5, 12, 'Wolves', null, null, null), null) ===
    'The Wolves are on the clock.',
  'the on-deck sentence is dropped at the end of the draft',
)
check(onClockAnnouncement(null, null) === null, 'no current pick produces no line')
check(endOfRoundAnnouncement(3) === 'End of Round 3.', 'end-of-round wording')

// ------------------------------------------------------- which picks
console.log('\n== Which picks still need saying ==')

/** A 3-round, 4-team board with the first `filled` picks made. */
const makeBoard = (filled, draftStarted = true) => {
  const picks = []
  for (let r = 1; r <= 3; r++) {
    for (let k = 1; k <= 4; k++) {
      const n = (r - 1) * 4 + k
      const made = n <= filled
      picks.push(
        pick(r, k, `Team${k}`, made ? `Player${n}` : null, made ? 'QB' : null, made ? 'BUF' : null),
      )
    }
  }

  const open = picks.filter((p) => p.playerName === null)

  return { draftStarted, picks, currentPick: open[0] ?? null, onDeckPick: open[1] ?? null }
}

const FLOOR_1 = pickKey(1, 1)

let plan = planPickAnnouncements(makeBoard(0).picks, FLOOR_1 - 1)
check(plan.lines.length === 0, 'a board with no picks made says nothing', plan.lines)

plan = planPickAnnouncements(makeBoard(1).picks, plan.last)
check(
  plan.lines.length === 1 && plan.lines[0].includes('Player1'),
  'a new pick is announced',
  plan.lines,
)

const steady = planPickAnnouncements(makeBoard(1).picks, plan.last)
check(steady.lines.length === 0, 'repeat poll with no change says nothing', steady.lines)

plan = planPickAnnouncements(makeBoard(4).picks, plan.last)
check(
  plan.lines.filter((l) => l === 'End of Round 1.').length === 1,
  'the last pick of a round is followed by exactly one end-of-round line',
  plan.lines,
)

plan = planPickAnnouncements(makeBoard(7).picks, plan.last)
check(
  plan.lines.filter((l) => l.includes('select')).length === 3,
  'three picks landing between polls announce all three',
  plan.lines,
)
check(
  plan.lines[0].includes('Player5') && plan.lines[2].includes('Player7'),
  'catch-up preserves draft order',
  plan.lines,
)

// The reload case this whole pointer mechanism exists for.
const reload = planPickAnnouncements(makeBoard(7).picks, plan.last)
check(
  reload.lines.length === 0,
  'reload with a restored pointer re-announces NO picks',
  reload.lines,
)

const cold = planPickAnnouncements(makeBoard(7).picks, FLOOR_1 - 1)
check(
  cold.lines.filter((l) => l.includes('select')).length === 7,
  'a pointerless reload at floor 1.1 would re-read everything — which is why the pointer exists',
  cold.lines.length,
)

const FLOOR_24 = pickKey(2, 4)
const moved = planPickAnnouncements(makeBoard(8).picks, FLOOR_24 - 1)
check(
  moved.lines.filter((l) => l.includes('select')).length === 1 && moved.lines[0].includes('Player8'),
  'a floor at 2.4 announces pick 2.4 and nothing before it',
  moved.lines,
)
check(
  moved.lines.includes('End of Round 2.'),
  'end-of-round still fires for a round the floor starts inside',
  moved.lines,
)

const atOpenFloor = planPickAnnouncements(makeBoard(7).picks, FLOOR_24 - 1)
check(
  atOpenFloor.lines.length === 0,
  'a floor pointing at an unfilled slot announces no picks',
  atOpenFloor.lines,
)

// -------------------------------------------------- Start Draft resets
console.log('\n== Start Draft invalidates a remembered pointer ==')

check(
  storedPointerApplies({ floor: FLOOR_1, last: 1005, startedAt: 111 }, FLOOR_1, 111),
  'a pointer from the same draft and floor still applies',
)
check(
  !storedPointerApplies({ floor: FLOOR_1, last: 1005, startedAt: 111 }, FLOOR_1, 222),
  'Start Draft re-stamps draft.full.start, so the old pointer is discarded',
)
check(
  !storedPointerApplies({ floor: FLOOR_1, last: 1005, startedAt: 111 }, FLOOR_24, 111),
  'moving the commish floor also discards it',
)
check(
  storedPointerApplies({ floor: FLOOR_1, last: 1005 }, FLOOR_1, 111),
  'an entry written before the stamp existed adopts the current draft rather than replaying',
)
check(!storedPointerApplies(null, FLOOR_1, 111), 'no stored pointer never applies')
check(
  storedPointerApplies({ floor: FLOOR_1, last: 1005, startedAt: null }, FLOOR_1, null),
  'a draft that has never been started matches on null',
)

// ------------------------------------------------------------- undo
console.log('\n== Undo and re-pick ==')

check(highestFilledPickKey(makeBoard(0).picks) === null, 'no filled picks yields null')
check(highestFilledPickKey(makeBoard(5).picks) === pickKey(2, 1), 'highest filled pick is found')

// Models takeNewPickLines' rollback: the commish undoes pick 5 (2.1), then
// that slot is picked again. Without the rollback the re-pick is treated as
// already handled and never announced — picks go silent while on-the-clock
// lines carry on, which is the reported symptom.
const takeWithRollback = (board, pointer) => {
  const highest = highestFilledPickKey(board.picks)
  const floorFor = Math.max(pointer.floor - 1, highest ?? pointer.floor - 1)
  if (pointer.last > floorFor) {
    pointer.last = floorFor
  }
  const plan = planPickAnnouncements(board.picks, pointer.last)
  pointer.last = plan.last
  return plan.lines
}

const undoPointer = { floor: FLOOR_1, last: FLOOR_1 - 1 }
takeWithRollback(makeBoard(5), undoPointer)
check(undoPointer.last === pickKey(2, 1), 'pointer keeps up through pick 2.1', undoPointer.last)

const afterUndo = takeWithRollback(makeBoard(4), undoPointer)
check(afterUndo.length === 0, 'the undo itself announces nothing', afterUndo)
check(undoPointer.last === pickKey(1, 4), 'pointer rolls back to the undone slot', undoPointer.last)

const afterRepick = takeWithRollback(makeBoard(5), undoPointer)
check(
  afterRepick.some((l) => l.includes('Player5,')),
  'the re-pick of the undone slot IS announced',
  afterRepick,
)

// Rolling back must never reach behind the commish's configured floor.
const flooredPointer = { floor: FLOOR_24, last: FLOOR_24 - 1 }
takeWithRollback(makeBoard(0), flooredPointer)
check(
  flooredPointer.last === FLOOR_24 - 1,
  'an empty board does not drag the pointer below the floor',
  flooredPointer.last,
)

// ------------------------------------------------------ who is on the clock
console.log('\n== Who is on the clock ==')

let onClock = planOnClockAnnouncement(makeBoard(0), FLOOR_1, null)
check(
  onClock.line !== null && onClock.line.includes('Team1'),
  'the team holding the first open pick is announced',
  onClock.line,
)
check(
  planOnClockAnnouncement(makeBoard(0), FLOOR_1, onClock.onClockKey).line === null,
  'the same slot is not announced twice',
)
check(
  planOnClockAnnouncement(makeBoard(2), pickKey(3, 1), null).line === null,
  'a floor ahead of the draft stays completely silent',
)
check(
  planOnClockAnnouncement(makeBoard(12), FLOOR_1, null).line === null,
  'no on-the-clock line once the draft is complete',
)

// Everyone is sitting around waiting before the commish presses Start Draft;
// team 1 is nominally on the clock the whole time, and saying so is noise.
const notStarted = planOnClockAnnouncement(makeBoard(0, false), FLOOR_1, null)
check(notStarted.line === null, 'nothing is announced before the draft is started', notStarted.line)
check(
  notStarted.onClockKey === null,
  'and the slot is left unmarked, so it still plays the moment the draft starts',
  notStarted.onClockKey,
)
check(
  planOnClockAnnouncement(makeBoard(0, true), FLOOR_1, notStarted.onClockKey).line !== null,
  'once started, the first team on the clock is announced',
)

// --------------------------------------------- ordering under live picks
console.log('\n== Ordering while picks land mid-announcement ==')

/**
 * Models the drain loop in useAnnouncer.ts: say every pick the current board
 * calls new, then re-fetch and look again, and only announce who's on the
 * clock once a fresh board has nothing left.
 *
 * `boardAt(t)` is the board visible after `t` utterances — each utterance
 * takes real time, which is exactly how picks sneak in mid-run.
 *
 * Note the single owner of `last`: takeNewPickLines advances it and returns
 * the lines in one step, so a pick can never be marked handled while its
 * lines go unspoken. `silentUntil` models the announcer being switched on
 * partway through — the one place lines are deliberately discarded.
 */
const runAnnouncer = (boardAt, { floor = FLOOR_1, silentUntil = 0 } = {}) => {
  let t = 0
  let last = floor - 1
  let onClockKey = null
  const spoken = []

  const takeNewPickLines = (board) => {
    const p = planPickAnnouncements(board.picks, last)
    if (p.lines.length === 0) {
      return []
    }
    last = p.last
    return p.lines
  }

  // Polls that arrive while the announcer is still off: pointer keeps up,
  // lines are dropped on purpose.
  for (let poll = 0; poll < silentUntil; poll++) {
    takeNewPickLines(boardAt(poll))
  }

  for (let guard = 0; guard < 500; guard++) {
    let lines = takeNewPickLines(boardAt(t))

    if (lines.length === 0) {
      lines = takeNewPickLines(boardAt(t)) // the re-fetch, same tick
    }

    if (lines.length === 0) {
      const p = planOnClockAnnouncement(boardAt(t), floor, onClockKey)
      onClockKey = p.onClockKey
      if (p.line === null) {
        break
      }
      lines = [p.line]
    }

    for (const line of lines) {
      spoken.push(line)
      t++
    }
  }

  return spoken
}

// Three picks are in when we start; one more lands per utterance, up to 6.
const spoken = runAnnouncer((t) => makeBoard(Math.min(3 + t, 6)))

check(
  spoken.filter((l) => l.includes('select')).length === 6,
  'every pick that landed during the run gets announced',
  spoken,
)

// The regression: an on-the-clock line planned from the same snapshot as the
// picks named a team that had already picked, and was then immediately
// followed by that team's selection.
//
// In this scenario picks land continuously, so the board is never actually
// caught up until the end — which makes "an on-the-clock line followed by a
// pick" proof of a stale snapshot. (It is NOT a universal rule: with a real
// pause between picks, "Team4 are on the clock" followed later by "Team4
// select…" is exactly right. See the switched-on-mid-draft case below.)
const badOrder = spoken.findIndex(
  (l, i) => l.includes('on the clock') && (spoken[i + 1] ?? '').includes('select'),
)
check(
  badOrder === -1,
  'during a burst, an on-the-clock line is never followed by a pick — the stale-snapshot bug',
  spoken,
)

check(
  spoken.filter((l) => l.includes('on the clock')).length === 1,
  'on-the-clock is announced exactly once, not once per catch-up round',
  spoken,
)
check(
  spoken[spoken.length - 1].includes('on the clock'),
  'on-the-clock is the last thing said, once the board is genuinely caught up',
  spoken[spoken.length - 1],
)
check(
  spoken[spoken.length - 1].includes('Team3') && spoken[spoken.length - 1].includes('Team4'),
  'the team named is the one holding the first still-open pick (2.3), with 2.4 on deck',
  spoken[spoken.length - 1],
)

// The announcer switched on partway through: the picks that landed while it
// was silent stay silent, but everything after must still be announced. The
// regression here was picks going silent *permanently* — the pointer moved
// on a poll that then discarded the lines, so nothing was ever read out and
// only on-the-clock lines played.
const lateStart = runAnnouncer((t) => makeBoard(Math.min(2 + t, 6)), { silentUntil: 2 })
check(
  lateStart.filter((l) => l.includes('select')).length > 0,
  'picks are still announced after the announcer is switched on mid-draft',
  lateStart,
)
check(
  !['Player1,', 'Player2,', 'Player3,'].some((p) => lateStart.some((l) => l.includes(p))),
  'picks made while it was switched off are not read out as a backlog',
  lateStart,
)
check(
  lateStart.some((l) => l.includes('Player4,')),
  'the first pick to land after switching on is announced',
  lateStart,
)

// A pick landing *exactly* as the run would otherwise finish must still be
// picked up before any on-the-clock line is spoken.
const lateSpoken = runAnnouncer((t) => makeBoard(t >= 1 ? 5 : 1))
check(
  lateSpoken.filter((l) => l.includes('select')).length === 5,
  'a pick landing just as the queue empties is caught by the re-check',
  lateSpoken,
)
check(
  lateSpoken[lateSpoken.length - 1].includes('on the clock'),
  'and the on-the-clock line still comes last',
  lateSpoken,
)

// A quiet board: nothing to catch up on, so the on-clock line is all there is.
const quiet = runAnnouncer(() => makeBoard(0))
check(
  quiet.length === 1 && quiet[0].includes('on the clock'),
  'with no picks at all, the announcer just says who is up',
  quiet,
)

console.log(`\n${pass} passed, ${fail} failed`)
process.exit(fail === 0 ? 0 : 1)
