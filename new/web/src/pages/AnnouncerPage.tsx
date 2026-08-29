import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage, type VoiceSettings } from '../api'
import { LoginCard } from '../components/LoginCard'
import { PicksGrid } from '../components/PicksGrid'
import { formatClock, posTeam } from '../format'
import { useAnnouncer } from '../useAnnouncer'
import { useSession } from '../session'
import type { BoardState } from '../useBoard'

// The voice-announcing board (docs/voice-announce-spec.md §5.3) — a third
// route, meant to be opened in its own window and screen-shared with audio
// into the draft-day video call. No app header or nav: everything on screen
// is something the room should be able to read at a glance.
//
// Commish-gated, like the console, because GET /api/commish/voice hands back
// the Cognito pool ID.

const SETTINGS_POLL_MS = 5_000

export function AnnouncerPage({ boardState }: { boardState: BoardState }) {
  const { session } = useSession()

  if (session === null) {
    return <p className="page-notice">Loading…</p>
  }
  if (!session.isin) {
    return (
      <div className="commish-login">
        <p className="page-notice">Log in as the commissioner to run the announcer.</p>
        <LoginCard />
      </div>
    )
  }
  if (!session.commish) {
    return <p className="page-notice">Commissioner access required.</p>
  }

  return <Announcer boardState={boardState} />
}

function Announcer({ boardState }: { boardState: BoardState }) {
  const { board, displaySeconds, error: boardError } = boardState
  const [settings, setSettings] = useState<VoiceSettings | null>(null)
  const [settingsError, setSettingsError] = useState<string | null>(null)

  // Polled rather than read once, so the commish can flip the toggle or
  // change voice from their own machine mid-draft and have this window
  // pick it up without anyone touching it.
  const loadSettings = useCallback(async () => {
    try {
      setSettings(await api.voiceSettings())
      setSettingsError(null)
    } catch (err) {
      setSettingsError(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    loadSettings().catch(() => {})
    const timer = setInterval(() => {
      loadSettings().catch(() => {})
    }, SETTINGS_POLL_MS)

    return () => clearInterval(timer)
  }, [loadSettings])

  const announcer = useAnnouncer(board, settings)

  const problem = settings?.configError ?? settingsError ?? announcer.error
  const idle = settings === null || !settings.enabled

  return (
    <div className="announcer">
      <header className="announcer-status">
        {board === null ? (
          <span className="announcer-headline">Loading draft board…</span>
        ) : board.currentPick === null ? (
          <span className="announcer-headline">Draft complete</span>
        ) : (
          <>
            <span className="announcer-slot">
              <small>Round</small>
              <strong>{board.currentPick.round}</strong>
            </span>
            <span className="announcer-slot">
              <small>Pick</small>
              <strong>{board.currentPick.pick}</strong>
            </span>
            <span
              className={`announcer-clock${board.clockRunning && displaySeconds <= 10 ? ' urgent' : ''}`}
            >
              {formatClock(displaySeconds)}
              {!board.clockRunning && <em className="announcer-paused">paused</em>}
            </span>
            <span className="announcer-slot grow">
              <small>On the clock</small>
              <strong>{board.currentPick.teamName}</strong>
            </span>
            <span className="announcer-slot">
              <small>On deck</small>
              <strong>{board.onDeckPick !== null ? board.onDeckPick.teamName : '—'}</strong>
            </span>
            <span className="announcer-slot grow">
              <small>Last pick</small>
              <strong>
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
              </strong>
            </span>
          </>
        )}
      </header>

      {boardError !== null && <p className="error-banner">Connection problem: {boardError}</p>}

      <main className="announcer-board">{board !== null && <PicksGrid board={board} />}</main>

      <footer className="announcer-controls">
        {!announcer.audioReady ? (
          <button type="button" className="btn btn-primary" onClick={announcer.enableAudio}>
            Enable Announcements
          </button>
        ) : (
          <button type="button" className="btn btn-small" onClick={announcer.enableAudio}>
            Test Sound
          </button>
        )}

        <span className={`announcer-lamp ${announcer.audioReady && !idle ? 'live' : 'off'}`}>
          {!announcer.audioReady
            ? 'Audio not enabled — click to start'
            : idle
              ? 'Announcements off'
              : announcer.speaking
                ? 'Announcing…'
                : 'Live'}
        </span>

        {settings !== null && <span className="muted">Voice: {settings.voiceId}</span>}

        <span className="announcer-said">
          {announcer.lastSpoken !== null ? `“${announcer.lastSpoken}”` : ''}
        </span>

        {problem !== null && problem !== undefined && <span className="error-text">{problem}</span>}
      </footer>
    </div>
  )
}
