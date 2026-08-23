import type { CSSProperties } from 'react'
import { ColumnResizer } from '../components/ColumnResizer'
import { LoginCard } from '../components/LoginCard'
import { MyPickPanel } from '../components/MyPickPanel'
import { PicksGrid } from '../components/PicksGrid'
import { RosterViewer } from '../components/RosterViewer'
import { TeamClocks } from '../components/TeamClocks'
import { useColumnResizer } from '../useColumnResizer'
import { useSession } from '../session'
import type { BoardState } from '../useBoard'

/** The public draft board — replaces draft.php/index.php. */
export function BoardPage({ boardState }: { boardState: BoardState }) {
  const { session } = useSession()
  const { board, error, refresh } = boardState
  const draftCol = useColumnResizer('wmffl.col.draftBoard', 360, 1000)
  const myPickCol = useColumnResizer('wmffl.col.myPick', 240, 640)

  if (board === null) {
    return <p className="page-notice">{error ?? 'Loading draft board…'}</p>
  }

  return (
    <div
      className="board-layout"
      style={{ '--draft-w': draftCol.width !== null ? `${draftCol.width}px` : undefined } as CSSProperties}
    >
      {error !== null && <p className="error-banner">Connection problem: {error}</p>}
      <PicksGrid board={board} />
      <ColumnResizer label="Resize Draft Board column" {...draftCol} />
      <div
        className="side-panels"
        style={{ '--mypick-w': myPickCol.width !== null ? `${myPickCol.width}px` : undefined } as CSSProperties}
      >
        {session !== null && session.isin && session.teamId !== null ? (
          <MyPickPanel board={board} session={session} refreshBoard={refresh} />
        ) : (
          <LoginCard />
        )}
        <ColumnResizer label="Resize My Pick column" {...myPickCol} />
        <div className="side-column">
          <TeamClocks board={board} displaySeconds={boardState.displaySeconds} />
          <RosterViewer board={board} />
        </div>
      </div>
    </div>
  )
}
