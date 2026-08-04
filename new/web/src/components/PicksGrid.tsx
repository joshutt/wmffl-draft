import { useEffect, useRef } from 'react'
import { posTeam } from '../format'
import type { Board } from '../api'

/** The full snake-draft pick list — replaces the legacy left-hand picks table. */
export function PicksGrid({ board }: { board: Board }) {
  const currentRef = useRef<HTMLTableRowElement | null>(null)
  const currentKey =
    board.currentPick !== null ? `${board.currentPick.round}-${board.currentPick.pick}` : null

  useEffect(() => {
    currentRef.current?.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }, [currentKey])

  return (
    <section className="card picks-card">
      <h2 className="card-title">Draft Board</h2>
      <div className="picks-scroll">
        <table className="picks-table">
          <thead>
            <tr>
              <th>Rd</th>
              <th>Pick</th>
              <th>Franchise</th>
              <th>Selection</th>
            </tr>
          </thead>
          <tbody>
            {board.picks.map((p) => {
              const key = `${p.round}-${p.pick}`
              const isCurrent = key === currentKey

              return (
                <tr
                  key={key}
                  ref={isCurrent ? currentRef : undefined}
                  className={isCurrent ? 'current-pick' : undefined}
                >
                  <td className="num">{p.round}</td>
                  <td className="num">{p.pick}</td>
                  <td>{p.teamName}</td>
                  <td>
                    {p.playerName !== null ? (
                      <>
                        {p.playerName}{' '}
                        <span className="muted">{posTeam(p.playerPos, p.playerTeam)}</span>
                      </>
                    ) : isCurrent ? (
                      <span className="on-clock-label">on the clock</span>
                    ) : (
                      ''
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </section>
  )
}
