import { formatClock, posTeam } from '../format'
import type { Board } from '../api'

/** The always-visible strip: round / pick / countdown / team on the clock / recent status. */
export function ClockBar({ board, displaySeconds }: { board: Board | null; displaySeconds: number }) {
  if (board === null) {
    return <div className="clock-bar">Loading draft board…</div>
  }

  if (board.currentPick === null) {
    return (
      <div className="clock-bar">
        <span className="clock-bar-team">Draft complete</span>
      </div>
    )
  }

  const urgent = board.clockRunning && displaySeconds <= 10

  return (
    <div className="clock-bar">
      <span className="clock-bar-slot">
        Round <strong>{board.currentPick.round}</strong>
      </span>
      <span className="clock-bar-slot">
        Pick <strong>{board.currentPick.pick}</strong>
      </span>
      <span className={`clock-bar-time${urgent ? ' urgent' : ''}`}>{formatClock(displaySeconds)}</span>
      {!board.clockRunning && <span className="clock-bar-paused">PAUSED</span>}
      <span className="clock-bar-slot">
        Last pick:{' '}
        {board.lastPick !== null ? (
          <strong>
            {board.lastPick.playerName}{' '}
            <span className="muted">{posTeam(board.lastPick.playerPos, board.lastPick.playerTeam)}</span> —{' '}
            {board.lastPick.teamName}
          </strong>
        ) : (
          <span className="muted">none yet</span>
        )}
      </span>
      <span className="clock-bar-slot">
        On the clock: <strong>{board.currentPick.teamName}</strong>
      </span>
      <span className="clock-bar-team">
        On deck: <strong>{board.onDeckPick !== null ? board.onDeckPick.teamName : '—'}</strong>
      </span>
    </div>
  )
}
