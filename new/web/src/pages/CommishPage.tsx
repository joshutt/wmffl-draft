import { useCallback, useEffect, useState } from 'react'
import {
  api,
  errorMessage,
  type OwnerStatus,
  type PriorityListSummary,
  type VoiceSettings,
  type VoiceSettingsPatch,
} from '../api'
import { LoginCard } from '../components/LoginCard'
import { formatClock } from '../format'
import { useSession } from '../session'
import type { BoardState } from '../useBoard'

// Commissioner console — replaces src/commish/ (presence table, start draft,
// start/stop clock, force auto-pick, undo last pick). Server-side every
// /api/commish/* call is re-checked by Guard::requireCommish(); this page
// gating is just UX.

const STATUS_POLL_MS = 5_000
const POSITIONS = ['QB', 'RB', 'WR', 'TE', 'K', 'OL', 'DL', 'LB', 'DB']

export function CommishPage({ boardState }: { boardState: BoardState }) {
  const { session } = useSession()

  if (session === null) {
    return <p className="page-notice">Loading…</p>
  }
  if (!session.isin) {
    return (
      <div className="commish-login">
        <p className="page-notice">Log in to use the commish tools.</p>
        <LoginCard />
      </div>
    )
  }
  if (!session.commish) {
    return <p className="page-notice">Commissioner access required.</p>
  }

  return <CommishConsole boardState={boardState} />
}

function CommishConsole({ boardState }: { boardState: BoardState }) {
  const { board, displaySeconds, refresh } = boardState
  const [owners, setOwners] = useState<OwnerStatus[]>([])
  const [statusError, setStatusError] = useState<string | null>(null)
  const [autoPos, setAutoPos] = useState('*')
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [hangoutInput, setHangoutInput] = useState<string | null>(null)
  const [prioritySummary, setPrioritySummary] = useState<PriorityListSummary | null>(null)
  const [priorityError, setPriorityError] = useState<string | null>(null)
  const [priorityFile, setPriorityFile] = useState<File | null>(null)
  const [voice, setVoice] = useState<VoiceSettings | null>(null)
  const [voiceError, setVoiceError] = useState<string | null>(null)
  const [voiceStart, setVoiceStart] = useState<{ round: string; pick: string } | null>(null)

  const loadStatus = useCallback(async () => {
    try {
      const { owners: rows } = await api.commishStatus()
      setOwners(rows)
      setStatusError(null)
    } catch (err) {
      setStatusError(errorMessage(err))
    }
  }, [])

  const loadPriorityList = useCallback(async () => {
    try {
      setPrioritySummary(await api.priorityList())
      setPriorityError(null)
    } catch (err) {
      setPriorityError(errorMessage(err))
    }
  }, [])

  // Loaded once, not polled: this page is the only writer, so its own state
  // is authoritative, and polling would fight the commish's typing in the
  // start round/pick fields the same way it would in the hangout URL field.
  const loadVoice = useCallback(async () => {
    try {
      const settings = await api.voiceSettings()
      setVoice(settings)
      setVoiceStart((current) =>
        current ?? { round: String(settings.startRound), pick: String(settings.startPick) },
      )
      setVoiceError(null)
    } catch (err) {
      setVoiceError(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    loadVoice().catch(() => {})
  }, [loadVoice])

  useEffect(() => {
    loadStatus().catch(() => {})
    loadPriorityList().catch(() => {})
    const timer = setInterval(() => {
      loadStatus().catch(() => {})
      loadPriorityList().catch(() => {})
    }, STATUS_POLL_MS)

    return () => clearInterval(timer)
  }, [loadStatus, loadPriorityList])

  // Seed the hangout-url field from the server once, the first time the
  // board loads — not on every poll, so it doesn't clobber the commish
  // mid-edit (see saveHangoutUrl, which pushes local edits back to state).
  useEffect(() => {
    if (hangoutInput === null && board !== null) {
      setHangoutInput(board.hangoutUrl ?? '')
    }
  }, [board, hangoutInput])

  const run = async (label: string, action: () => Promise<unknown>) => {
    setBusy(true)
    setMessage(null)
    try {
      await action()
      setMessage(label)
    } catch (err) {
      setMessage(`Error: ${errorMessage(err)}`)
    } finally {
      setBusy(false)
      await Promise.all([refresh(), loadStatus()]).catch(() => {})
    }
  }

  const startDraft = () => {
    if (
      window.confirm(
        'Start the draft? This resets every team clock to the full budget, and restarts voice ' +
          'announcing from the start round/pick set below.',
      )
    ) {
      run('Draft started', api.startDraft).catch(() => {})
    }
  }

  const toggleClock = () => {
    if (board === null) {
      return
    }
    if (board.clockRunning) {
      run('Clock stopped', api.stopClock).catch(() => {})
    } else {
      run('Clock started', api.startClock).catch(() => {})
    }
  }

  const undo = () => {
    if (window.confirm('Undo the last pick?')) {
      run('Last pick undone', api.undoPick).catch(() => {})
    }
  }

  const saveHangoutUrl = () => {
    if (hangoutInput === null) {
      return
    }
    run('Hangout link updated', () => api.setHangoutUrl(hangoutInput)).catch(() => {})
  }

  const uploadPriorityList = () => {
    if (priorityFile === null) {
      return
    }
    const file = priorityFile
    run('Priority list imported', async () => {
      const result = await api.uploadPriorityList(file)
      setPrioritySummary(result)
      setMessage(
        result.pending.length > 0
          ? `Imported ${result.imported} row(s), ${result.pending.length} pending`
          : `Imported ${result.imported} row(s)`,
      )
    }).catch(() => {})
    setPriorityFile(null)
  }

  const saveVoice = (patch: VoiceSettingsPatch, label: string) => {
    run(label, async () => {
      // The POST returns the full settings, so the response is the new
      // authoritative state — no refetch needed.
      setVoice(await api.saveVoiceSettings(patch))
    }).catch(() => {})
  }

  const saveVoiceStart = () => {
    if (voice === null || voiceStart === null) {
      return
    }

    const round = Number.parseInt(voiceStart.round, 10)
    const pick = Number.parseInt(voiceStart.pick, 10)
    if (!Number.isFinite(round) || !Number.isFinite(pick) || round < 1 || pick < 1) {
      // Snap the fields back rather than posting something the API will
      // reject — a half-typed value shouldn't become an error banner.
      setVoiceStart({ round: String(voice.startRound), pick: String(voice.startPick) })
      return
    }
    if (round === voice.startRound && pick === voice.startPick) {
      return
    }

    saveVoice({ startRound: round, startPick: pick }, `Announcing from round ${round}, pick ${pick}`)
  }

  const autoPick = (owner: OwnerStatus) => {
    const posLabel = autoPos === '*' ? 'best available' : autoPos
    if (!window.confirm(`Auto-pick (${posLabel}) for ${owner.teamName}?`)) {
      return
    }

    run(`Auto-picked for ${owner.teamName}`, async () => {
      const result = await api.autoPick(owner.teamId, autoPos === '*' ? null : autoPos)
      setMessage(
        result.player !== null
          ? `${result.queued ? 'Queued' : 'Picked'} ${result.player.name} for ${owner.teamName}`
          : `Auto-pick submitted for ${owner.teamName}`,
      )
    }).catch(() => {})
    setAutoPos('*')
  }

  const totalDraftSeconds =
    board !== null && board.draftStartedAt !== null ? board.serverTime - board.draftStartedAt : null

  return (
    <div className="commish-layout">
      <div className="commish-left">
        <section className="card">
          <h2 className="card-title">Draft Controls</h2>
          {board === null ? (
            <p className="muted">Loading clock…</p>
          ) : (
            <>
              <p className="commish-clock-status">
                Clock is <strong>{board.clockRunning ? 'running' : 'stopped'}</strong>
                {board.currentPick !== null && (
                  <>
                    {' '}
                    — {board.currentPick.teamName}{' '}
                    <span className="muted">
                      (round {board.currentPick.round}, pick {board.currentPick.pick})
                    </span>
                  </>
                )}
              </p>
              <div className="commish-clock-big">{formatClock(displaySeconds)}</div>
              <div className="commish-buttons">
                <button
                  type="button"
                  className={`btn ${board.clockRunning ? 'btn-danger' : 'btn-primary'}`}
                  onClick={toggleClock}
                  disabled={busy || board.currentPick === null}
                >
                  {board.clockRunning ? 'Stop Clock' : 'Start Clock'}
                </button>
                <button type="button" className="btn" onClick={undo} disabled={busy}>
                  Undo Pick
                </button>
                <button type="button" className="btn btn-danger" onClick={startDraft} disabled={busy}>
                  Start Draft
                </button>
              </div>
              {totalDraftSeconds !== null && (
                <p className="muted">Total draft time: {formatClock(totalDraftSeconds)}</p>
              )}
            </>
          )}
          {message !== null && <p className="pick-message">{message}</p>}
        </section>

        <section className="card">
          <h2 className="card-title">Google Hangout</h2>
          <label className="hangout-url-row">
            Meeting link
            <input
              type="text"
              value={hangoutInput ?? ''}
              placeholder="meet.google.com/abc-defg-hij"
              onChange={(e) => setHangoutInput(e.target.value)}
              onBlur={saveHangoutUrl}
              disabled={busy}
            />
          </label>
        </section>

        <section className="card">
          <h2 className="card-title">Voice Announcer</h2>
          {voiceError !== null && <p className="error-text">{voiceError}</p>}
          {voice === null || voiceStart === null ? (
            <p className="muted">Loading…</p>
          ) : (
            <>
              <label className="voice-row">
                <input
                  type="checkbox"
                  checked={voice.enabled}
                  disabled={busy}
                  onChange={(e) =>
                    saveVoice(
                      { enabled: e.target.checked },
                      e.target.checked ? 'Announcements on' : 'Announcements off',
                    )
                  }
                />
                Announce picks aloud
              </label>

              <label className="voice-row">
                Voice
                <select
                  value={voice.voiceId}
                  disabled={busy}
                  onChange={(e) => saveVoice({ voiceId: e.target.value }, `Voice: ${e.target.value}`)}
                >
                  {voice.voices.map((v) => (
                    <option key={v} value={v}>
                      {v}
                    </option>
                  ))}
                </select>
              </label>

              <div className="voice-start-row">
                <label>
                  Start at round
                  <input
                    type="number"
                    min={1}
                    value={voiceStart.round}
                    disabled={busy}
                    onChange={(e) => setVoiceStart({ ...voiceStart, round: e.target.value })}
                    onBlur={saveVoiceStart}
                  />
                </label>
                <label>
                  pick
                  <input
                    type="number"
                    min={1}
                    value={voiceStart.pick}
                    disabled={busy}
                    onChange={(e) => setVoiceStart({ ...voiceStart, pick: e.target.value })}
                    onBlur={saveVoiceStart}
                  />
                </label>
              </div>
              <p className="muted">
                Where announcing resumes if the announcer window has to be reloaded.
              </p>

              <div className="voice-footer">
                <a className="btn btn-small" href="/announcer" target="_blank" rel="noreferrer">
                  Open Announcer ↗
                </a>
                {voice.configError !== null ? (
                  <span className="error-text">{voice.configError}</span>
                ) : (
                  <span className="muted">Polly via {voice.region} — pool configured</span>
                )}
              </div>
            </>
          )}
        </section>

        <section className="card">
          <h2 className="card-title">Auto-Draft Priority Lists</h2>
          {priorityError !== null && <p className="error-text">{priorityError}</p>}
          <div className="priority-upload-row">
            <input
              type="file"
              accept=".csv,text/csv"
              onChange={(e) => setPriorityFile(e.target.files?.[0] ?? null)}
              disabled={busy}
            />
            <button
              type="button"
              className="btn btn-small"
              onClick={uploadPriorityList}
              disabled={busy || priorityFile === null}
            >
              Upload CSV
            </button>
          </div>
          {prioritySummary !== null && (
            <>
              <table className="priority-counts-table">
                <thead>
                  <tr>
                    {POSITIONS.map((p) => (
                      <th key={p}>{p}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    {POSITIONS.map((p) => (
                      <td key={p} className="num">
                        {prioritySummary.counts[p] ?? 0}
                      </td>
                    ))}
                  </tr>
                </tbody>
              </table>
              {prioritySummary.pending.length > 0 && (
                <div className="priority-pending">
                  <p className="muted">Unmatched rows ({prioritySummary.pending.length}):</p>
                  <ul>
                    {prioritySummary.pending.map((row, i) => (
                      <li key={i}>
                        {row.pos} #{row.rank} — {row.name} ({row.reason})
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </section>
      </div>

      <section className="card">
        <h2 className="card-title">Owners</h2>
        {statusError !== null && <p className="error-text">{statusError}</p>}
        <div className="auto-pos-row">
          <label>
            Auto-pick position:{' '}
            <select value={autoPos} onChange={(e) => setAutoPos(e.target.value)}>
              <option value="*">Best available</option>
              {POSITIONS.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </label>
        </div>
        <table className="presence-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Team</th>
              <th>In</th>
              <th>Time</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {owners.map((o) => (
              <tr key={`${o.userId}-${o.teamId}`}>
                <td>{o.userName}</td>
                <td>{o.teamName}</td>
                <td>
                  <span
                    className={`presence-dot ${o.isIn ? 'in' : 'out'}`}
                    title={o.lastSeen ?? 'never seen'}
                  />
                </td>
                <td className="num">{formatClock(o.remainingSeconds)}</td>
                <td>
                  <button
                    type="button"
                    className="btn btn-small btn-danger"
                    onClick={() => autoPick(o)}
                    disabled={busy}
                  >
                    AUTO
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  )
}
