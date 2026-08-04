import { useCallback, useEffect, useMemo, useState } from 'react'
import { api, errorMessage, ApiError, type Board, type Player, type Session } from '../api'
import { posTeam } from '../format'

// Pick submission + hold/preselect UI — replaces the legacy "My Pick" card
// (playerList.php dropdown + setPick.php + clearSelection.php). The full
// available-player list is fetched once and filtered client-side; it
// refetches whenever a pick lands so drafted players drop off.

const POSITIONS = ['QB', 'RB', 'WR', 'TE', 'K', 'OL', 'DL', 'LB', 'DB']

interface Props {
  board: Board
  session: Session
  refreshBoard: () => Promise<void>
}

export function MyPickPanel({ board, session, refreshBoard }: Props) {
  const [players, setPlayers] = useState<Player[]>([])
  const [pos, setPos] = useState('*')
  const [nfl, setNfl] = useState('*')
  const [search, setSearch] = useState('')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<string | null>(null)

  const filledPickCount = board.picks.filter((p) => p.playerId !== null).length

  const loadPlayers = useCallback(async () => {
    try {
      const { players: list } = await api.players()
      setPlayers(list)
    } catch (err) {
      setMessage(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    loadPlayers().catch(() => {})
  }, [loadPlayers, filledPickCount])

  const nflTeams = useMemo(
    () => [...new Set(players.map((p) => p.nflTeam))].sort(),
    [players],
  )

  const visiblePlayers = useMemo(() => {
    const needle = search.trim().toLowerCase()

    return players.filter(
      (p) =>
        (pos === '*' || p.pos === pos) &&
        (nfl === '*' || p.nflTeam === nfl) &&
        (needle === '' ||
          `${p.firstName ?? ''} ${p.lastName}`.toLowerCase().includes(needle)),
    )
  }, [players, pos, nfl, search])

  // Only "on the clock" once the draft has started — before that, everything
  // is a queued preselection (startDraft runs checkPreselect on the queue).
  const onClock =
    board.draftStarted && board.currentPick !== null && board.currentPick.teamId === session.teamId
  const selected = players.find((p) => p.id === selectedId) ?? null

  const submit = async () => {
    if (selected === null) {
      return
    }

    const name = `${selected.firstName ?? ''} ${selected.lastName}`.trim()
    const label = `${name} ${posTeam(selected.pos, selected.nflTeam)}`
    const prompt = onClock
      ? `Draft ${label} with pick ${board.currentPick?.round}.${board.currentPick?.pick}?`
      : `Queue ${label} as your pick for when you're on the clock?`
    if (!window.confirm(prompt)) {
      return
    }

    setBusy(true)
    setMessage(null)
    try {
      if (onClock) {
        await api.submitPick(selected.id)
        setMessage(`Drafted ${label}`)
      } else {
        await api.holdPick(selected.id)
        setMessage(`Queued ${label}`)
      }
      setSelectedId(null)
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) {
        // Someone beat us to the player (the §6 race returns 409) — refresh
        // so the list reflects reality instead of silently disagreeing.
        setMessage(`${err.message} — refreshing the board.`)
      } else {
        setMessage(errorMessage(err))
      }
    } finally {
      setBusy(false)
      await Promise.all([refreshBoard(), loadPlayers()]).catch(() => {})
    }
  }

  const clearHold = async () => {
    setBusy(true)
    try {
      await api.clearHold()
      setMessage('Cleared queued pick')
    } catch (err) {
      setMessage(errorMessage(err))
    } finally {
      setBusy(false)
      await refreshBoard().catch(() => {})
    }
  }

  return (
    <section className={`card my-pick${onClock ? ' my-pick-active' : ''}`}>
      <h2 className="card-title">
        My Pick
        {onClock && <span className="badge-on-clock">You're on the clock!</span>}
      </h2>

      <div className="hold-row">
        {board.myHold !== null ? (
          <>
            <span>
              Queued: <strong>{board.myHold.name}</strong>{' '}
              <span className="muted">{posTeam(board.myHold.pos, board.myHold.nflTeam)}</span>
            </span>
            <button type="button" className="btn btn-small" onClick={clearHold} disabled={busy}>
              Clear
            </button>
          </>
        ) : (
          <span className="muted">No current selection</span>
        )}
      </div>

      <div className="player-filters">
        <select value={pos} onChange={(e) => setPos(e.target.value)} aria-label="Position filter">
          <option value="*">All Pos</option>
          {POSITIONS.map((p) => (
            <option key={p} value={p}>
              {p}
            </option>
          ))}
        </select>
        <select value={nfl} onChange={(e) => setNfl(e.target.value)} aria-label="NFL team filter">
          <option value="*">All Teams</option>
          {nflTeams.map((t) => (
            <option key={t} value={t}>
              {t}
            </option>
          ))}
        </select>
        <input
          type="search"
          placeholder="Search players…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      <ul className="player-list" role="listbox" aria-label="Available players">
        {visiblePlayers.map((p) => (
          <li key={p.id}>
            <button
              type="button"
              role="option"
              aria-selected={p.id === selectedId}
              className={p.id === selectedId ? 'selected' : undefined}
              onClick={() => setSelectedId(p.id)}
            >
              <span>
                {p.lastName}
                {p.firstName !== null && p.firstName !== '' ? `, ${p.firstName}` : ''}
              </span>
              <span className="muted">
                {p.pos} – {p.nflTeam}
              </span>
            </button>
          </li>
        ))}
        {visiblePlayers.length === 0 && <li className="muted empty">No players match</li>}
      </ul>

      <button
        type="button"
        className="btn btn-primary btn-block"
        onClick={submit}
        disabled={busy || selected === null}
      >
        {selected === null
          ? 'Select a player'
          : onClock
            ? `Draft ${selected.lastName}`
            : `Queue ${selected.lastName}`}
      </button>

      {message !== null && <p className="pick-message">{message}</p>}
    </section>
  )
}
