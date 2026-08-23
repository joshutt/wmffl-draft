import { useEffect, useState } from 'react'
import { api, errorMessage, type Board, type Roster } from '../api'

/**
 * Per-team roster viewer — replaces rosterBlock.php/rosterHtml.php. Team
 * tabs come from the season's teams (abbrevs were hardcoded per-team in the
 * legacy draft.php markup). Refetches the shown roster when picks land so
 * it stays current during the draft.
 */
export function RosterViewer({ board }: { board: Board }) {
  const [teamId, setTeamId] = useState<number | null>(null)
  const [roster, setRoster] = useState<Roster | null>(null)
  const [error, setError] = useState<string | null>(null)

  const filledPickCount = board.picks.filter((p) => p.playerId !== null).length

  useEffect(() => {
    if (teamId === null) {
      return
    }

    let stale = false
    api
      .roster(teamId)
      .then((r) => {
        if (!stale) {
          setRoster(r)
          setError(null)
        }
      })
      .catch((err: unknown) => {
        if (!stale) {
          setError(errorMessage(err))
        }
      })

    return () => {
      stale = true
    }
  }, [teamId, filledPickCount])

  return (
    <section className="card roster-card">
      <h2 className="card-title">Rosters</h2>
      <div className="roster-tabs">
        {board.teamClocks.map((t) => (
          <button
            key={t.teamId}
            type="button"
            title={t.name}
            className={`tab${t.teamId === teamId ? ' active' : ''}`}
            onClick={() => setTeamId(t.teamId)}
          >
            {t.abbrev}
          </button>
        ))}
      </div>
      {error !== null && <p className="error-text">{error}</p>}
      {teamId === null && <p className="muted">Select a team to view its roster.</p>}
      {roster !== null && teamId !== null && (
        <table className="roster-table">
          <thead>
            <tr>
              <th colSpan={4}>{roster.teamName}</th>
            </tr>
          </thead>
          <tbody>
            {roster.players.length === 0 && (
              <tr>
                <td colSpan={4} className="muted">
                  No players yet
                </td>
              </tr>
            )}
            {roster.players.map((p, i) => (
              <tr key={i}>
                <td className="pos">{p.pos}</td>
                <td>{p.name}</td>
                <td className="muted">{p.nflTeam}</td>
                <td className="muted num">{p.byeWeek !== null ? `Bye ${p.byeWeek}` : ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
