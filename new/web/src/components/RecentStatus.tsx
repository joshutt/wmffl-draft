import { posTeam } from '../format'
import type { Board } from '../api'

/** Last pick + on-deck team — the legacy "Recent Status" card. */
export function RecentStatus({ board }: { board: Board }) {
  return (
    <section className="card">
      <h2 className="card-title">Recent Status</h2>
      <dl className="status-list">
        <div>
          <dt>Last Pick</dt>
          <dd>
            {board.lastPick !== null ? (
              <>
                {board.lastPick.playerName}{' '}
                <span className="muted">
                  {posTeam(board.lastPick.playerPos, board.lastPick.playerTeam)}
                </span>{' '}
                — {board.lastPick.teamName}
              </>
            ) : (
              <span className="muted">none yet</span>
            )}
          </dd>
        </div>
        <div>
          <dt>On Deck</dt>
          <dd>
            {board.onDeckPick !== null ? (
              board.onDeckPick.teamName
            ) : (
              <span className="muted">—</span>
            )}
          </dd>
        </div>
      </dl>
    </section>
  )
}
