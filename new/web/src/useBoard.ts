import { useCallback, useEffect, useState } from 'react'
import { api, errorMessage, type Board } from './api'

// Polls GET /api/draft/board (picks grid + clock + hold merged — one call
// replacing the legacy picks.php 5s poll + clockService.php 15s poll) and
// runs the 1s local countdown between polls, like the legacy runClock().

const POLL_MS = 5_000

export interface BoardState {
  board: Board | null
  /** Locally ticked seconds remaining on the current pick, resynced on each poll */
  displaySeconds: number
  error: string | null
  refresh: () => Promise<void>
}

export function useBoard(): BoardState {
  const [board, setBoard] = useState<Board | null>(null)
  const [displaySeconds, setDisplaySeconds] = useState(0)
  const [error, setError] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    try {
      const next = await api.board()
      setBoard(next)
      setDisplaySeconds(next.timeRemaining)
      setError(null)
    } catch (err) {
      setError(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    refresh().catch(() => {})
    const timer = setInterval(() => {
      refresh().catch(() => {})
    }, POLL_MS)

    return () => clearInterval(timer)
  }, [refresh])

  const clockRunning = board?.clockRunning ?? false
  useEffect(() => {
    if (!clockRunning) {
      return
    }

    const timer = setInterval(() => {
      setDisplaySeconds((s) => Math.max(0, s - 1))
    }, 1_000)

    return () => clearInterval(timer)
  }, [clockRunning])

  return { board, displaySeconds, error, refresh }
}
