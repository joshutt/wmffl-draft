import { LoginCard } from '../components/LoginCard'
import { MyPickPanel } from '../components/MyPickPanel'
import { PicksGrid } from '../components/PicksGrid'
import { RecentStatus } from '../components/RecentStatus'
import { RosterViewer } from '../components/RosterViewer'
import { TeamClocks } from '../components/TeamClocks'
import { useSession } from '../session'
import type { BoardState } from '../useBoard'

/** The public draft board — replaces draft.php/index.php. */
export function BoardPage({ boardState }: { boardState: BoardState }) {
  const { session } = useSession()
  const { board, error, refresh } = boardState

  if (board === null) {
    return <p className="page-notice">{error ?? 'Loading draft board…'}</p>
  }

  return (
    <div className="board-layout">
      {error !== null && <p className="error-banner">Connection problem: {error}</p>}
      <PicksGrid board={board} />
      <div className="side-panels">
        {session !== null && session.isin && session.teamId !== null ? (
          <MyPickPanel board={board} session={session} refreshBoard={refresh} />
        ) : (
          <LoginCard />
        )}
        <RecentStatus board={board} />
        <TeamClocks board={board} displaySeconds={boardState.displaySeconds} />
        <RosterViewer board={board} />
      </div>
    </div>
  )
}
