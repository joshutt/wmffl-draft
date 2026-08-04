import { ClockBar } from './components/ClockBar'
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

  return (
    <>
      <header className="app-header">
        <span className="app-brand">WMFFL Live Draft</span>
        <nav className="app-nav">
          <Link to="/" className={!onCommish ? 'active' : undefined}>
            Board
          </Link>
          {session?.commish === true && (
            <Link to="/commish" className={onCommish ? 'active' : undefined}>
              Commish
            </Link>
          )}
        </nav>
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
