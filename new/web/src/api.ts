// Typed client for the new PHP JSON API (new/api). All paths are same-origin
// under /api/ — the vite dev server proxies them to the local PHP server
// (see vite.config.ts); in production .htaccess routes them to the front
// controller in the same webroot.

export interface Session {
  isin: boolean
  teamId: number | null
  userId: number | null
  name: string | null
  commish: boolean
}

export interface BoardPick {
  round: number
  pick: number
  teamId: number
  teamName: string
  playerId: number | null
  playerName: string | null
  playerPos: string | null
  playerTeam: string | null
  pickTime: number | null
}

export interface TeamClock {
  teamId: number
  name: string
  abbrev: string
  seconds: number
}

export interface HoldPlayer {
  playerId: number
  name: string
  pos: string | null
  nflTeam: string | null
}

export interface Board {
  season: number
  draftStarted: boolean
  clockRunning: boolean
  currentPick: BoardPick | null
  onDeckPick: BoardPick | null
  lastPick: BoardPick | null
  timeRemaining: number
  picks: BoardPick[]
  teamClocks: TeamClock[]
  myHold: HoldPlayer | null
  serverTime: number
  draftStartedAt: number | null
  hangoutUrl: string | null
}

export interface Player {
  id: number
  firstName: string | null
  lastName: string
  pos: string | null
  nflTeam: string
}

export interface RosterPlayer {
  pos: string | null
  name: string
  nflTeam: string | null
  byeWeek: number | null
}

export interface Roster {
  teamId: number
  teamName: string
  players: RosterPlayer[]
}

export interface OwnerStatus {
  userId: number
  userName: string
  teamId: number
  teamName: string
  isIn: boolean
  lastSeen: string | null
  remainingSeconds: number
}

export interface AutoPickResult {
  ok: boolean
  queued: boolean
  player: HoldPlayer | null
}

export interface PriorityListPendingRow {
  pos: string
  rank: number
  name: string
  reason: string
}

export interface PriorityListSummary {
  counts: Record<string, number>
  pending: PriorityListPendingRow[]
}

export interface PriorityListImportResult extends PriorityListSummary {
  imported: number
}

export interface VoiceSettings {
  enabled: boolean
  voiceId: string
  startRound: number
  startPick: number
  /** null when the host's db.ini has no usable [Voice_Values] — see configError */
  poolId: string | null
  region: string | null
  voices: string[]
  configError: string | null
}

/** Every field of VoiceSettings the commish console is allowed to write. */
export type VoiceSettingsPatch = Partial<
  Pick<VoiceSettings, 'enabled' | 'voiceId' | 'startRound' | 'startPick'>
>

export class ApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  // FormData bodies (the priority-list CSV upload) set their own multipart
  // boundary in the Content-Type header — forcing application/json here
  // would destroy it, so only JSON bodies get the header.
  const isFormData = init?.body instanceof FormData
  const res = await fetch(path, {
    credentials: 'same-origin',
    ...init,
    headers: init?.body && !isFormData ? { 'Content-Type': 'application/json' } : undefined,
  })

  let data: unknown = null
  try {
    data = await res.json()
  } catch {
    // non-JSON body (e.g. proxy error page) — fall through to status check
  }

  if (!res.ok) {
    const message = (data as { error?: string } | null)?.error ?? `Request failed (${res.status})`
    throw new ApiError(message, res.status)
  }

  return data as T
}

export const api = {
  session: () => request<Session>('/api/session'),
  login: (username: string, password: string) =>
    request<Session>('/api/auth/login', { method: 'POST', body: JSON.stringify({ username, password }) }),
  logout: () => request<{ ok: boolean }>('/api/auth/logout', { method: 'POST' }),
  heartbeat: () => request<{ ok: boolean }>('/api/session/heartbeat', { method: 'POST' }),

  board: () => request<Board>('/api/draft/board'),
  submitPick: (playerId: number) =>
    request<{ ok: boolean }>('/api/draft/pick', { method: 'POST', body: JSON.stringify({ playerId }) }),
  holdPick: (playerId: number) =>
    request<{ ok: boolean }>('/api/draft/hold', { method: 'POST', body: JSON.stringify({ playerId }) }),
  clearHold: () => request<{ ok: boolean }>('/api/draft/hold', { method: 'DELETE' }),

  players: () => request<{ players: Player[] }>('/api/players'),
  roster: (teamId: number) => request<Roster>(`/api/roster/${teamId}`),

  commishStatus: () => request<{ owners: OwnerStatus[] }>('/api/commish/status'),
  startDraft: () => request<{ ok: boolean }>('/api/commish/draft/start', { method: 'POST' }),
  startClock: () => request<{ ok: boolean }>('/api/commish/clock/start', { method: 'POST' }),
  stopClock: () => request<{ ok: boolean }>('/api/commish/clock/stop', { method: 'POST' }),
  autoPick: (teamId: number, pos: string | null) =>
    request<AutoPickResult>('/api/commish/pick/auto', {
      method: 'POST',
      body: JSON.stringify(pos ? { teamId, pos } : { teamId }),
    }),
  undoPick: () =>
    request<{ ok: boolean; undone: { round: number; pick: number } }>('/api/commish/pick/undo', {
      method: 'POST',
    }),
  setHangoutUrl: (url: string) =>
    request<{ ok: boolean }>('/api/commish/hangout-url', { method: 'POST', body: JSON.stringify({ url }) }),

  voiceSettings: () => request<VoiceSettings>('/api/commish/voice'),
  saveVoiceSettings: (patch: VoiceSettingsPatch) =>
    request<VoiceSettings>('/api/commish/voice', { method: 'POST', body: JSON.stringify(patch) }),

  priorityList: () => request<PriorityListSummary>('/api/commish/autodraft/priority'),
  uploadPriorityList: (file: File) => {
    const body = new FormData()
    body.append('file', file)
    return request<PriorityListImportResult>('/api/commish/autodraft/priority', { method: 'POST', body })
  },
}

export function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : String(err)
}
