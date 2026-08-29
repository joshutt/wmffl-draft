import { ClockBar } from './components/ClockBar'
import { hangoutHref } from './format'
import { AnnouncerPage } from './pages/AnnouncerPage'
import { BoardPage } from './pages/BoardPage'
import { CommishPage } from './pages/CommishPage'
import { Link, usePath } from './router'
import { SessionProvider, useSession } from './session'
import { useBoard } from './useBoard'

export default function App() {
  return (
    <SessionProvider>
      <Shell />
    </SessionProvider>
  )
}

function Shell() {
  const path = usePath()
  const boardState = useBoard()
  const { session, logout } = useSession()
  const onCommish = path.startsWith('/commish')
  const onAnnouncer = path.startsWith('/announcer')

  // The announcer window is screen-shared into the video call, so it gets no
  // app chrome at all — no nav, no login strip, and its own larger status
  // line in place of ClockBar (docs/voice-announce-spec.md §5.4). It still
  // rides the same useBoard() poll as every other route.
  if (onAnnouncer) {
    return <AnnouncerPage boardState={boardState} />
  }

  return (
    <>
      <header className="app-header">
        <span className="app-brand">WMFFL Live Draft</span>
        <nav className="app-nav">
          <Link to="/" className={!onCommish ? 'active' : undefined}>
            Board
          </Link>
          {session?.commish === true && (
            <>
              <Link to="/commish" className={onCommish ? 'active' : undefined}>
                Commish
              </Link>
              <Link to="/announcer">Announcer</Link>
            </>
          )}
        </nav>
        {boardState.board?.hangoutUrl != null && (
          <a
            className="app-hangout-link"
            href={hangoutHref(boardState.board.hangoutUrl)}
            target="_blank"
            rel="noreferrer"
          >
            Join the Google Hangout
          </a>
        )}
        {session !== null && session.isin && (
          <span className="app-user">
            {session.name}
            <button type="button" className="btn btn-small" onClick={() => logout().catch(() => {})}>
              Log Out
            </button>
          </span>
        )}
      </header>
      <ClockBar board={boardState.board} displaySeconds={boardState.displaySeconds} />
      <main className="app-main">
        {onCommish ? <CommishPage boardState={boardState} /> : <BoardPage boardState={boardState} />}
      </main>
    </>
  )
}
