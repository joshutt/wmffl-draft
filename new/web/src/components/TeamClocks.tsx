import { formatClock } from '../format'
import type { Board } from '../api'

/**
 * Every team's remaining clock. The on-the-clock team shows the live
 * countdown (matching the legacy runClock() behavior of ticking that team's
 * cell); everyone else shows their banked time from the last poll.
 */
export function TeamClocks({ board, displaySeconds }: { board: Board; displaySeconds: number }) {
  const onClockTeamId = board.currentPick?.teamId ?? null

  return (
    <section className="card">
      <h2 className="card-title">Clocks</h2>
      <ul className="team-clocks">
        {board.teamClocks.map((t) => {
          const isOnClock = t.teamId === onClockTeamId

          return (
            <li key={t.teamId} className={isOnClock ? 'on-clock' : undefined} title={t.name}>
              <span className="team-name">{t.abbrev}</span>
              <span className="team-time">
                {formatClock(isOnClock && board.clockRunning ? displaySeconds : t.seconds)}
              </span>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
