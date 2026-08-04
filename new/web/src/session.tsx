import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import { api, ApiError, type Session } from './api'

// Session state + presence heartbeat. The heartbeat POST replaces
// stillHere.php's 10s poll: it stamps draft.login.<userid> so the commish
// presence table shows the owner as "In".

const HEARTBEAT_MS = 10_000

interface SessionContextValue {
  /** null while the initial GET /api/session is in flight */
  session: Session | null
  login: (username: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

const SessionContext = createContext<SessionContextValue | null>(null)

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null)

  const refresh = useCallback(async () => {
    setSession(await api.session())
  }, [])

  useEffect(() => {
    refresh().catch(() => {
      // API unreachable on load — leave session null; the board poll surfaces
      // the error and the next successful poll cycle will retry login state.
    })
  }, [refresh])

  useEffect(() => {
    if (!session?.isin) {
      return
    }

    const timer = setInterval(() => {
      api.heartbeat().catch((err: unknown) => {
        if (err instanceof ApiError && err.status === 401) {
          // Server session expired — reflect it so the login card reappears.
          refresh().catch(() => {})
        }
      })
    }, HEARTBEAT_MS)

    return () => clearInterval(timer)
  }, [session?.isin, refresh])

  const login = useCallback(async (username: string, password: string) => {
    setSession(await api.login(username, password))
  }, [])

  const logout = useCallback(async () => {
    await api.logout()
    setSession({ isin: false, teamId: null, userId: null, name: null, commish: false })
  }, [])

  return (
    <SessionContext.Provider value={{ session, login, logout, refresh }}>
      {children}
    </SessionContext.Provider>
  )
}

export function useSession(): SessionContextValue {
  const ctx = useContext(SessionContext)
  if (ctx === null) {
    throw new Error('useSession must be used inside <SessionProvider>')
  }

  return ctx
}
