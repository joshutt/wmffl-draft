/** Seconds → m:ss, matching the legacy convertTime() display. */
export function formatClock(totalSeconds: number): string {
  const secs = Math.max(0, Math.floor(totalSeconds))
  const min = Math.floor(secs / 60)
  const sec = secs % 60

  return `${min}:${String(sec).padStart(2, '0')}`
}

/** Player position/NFL-team suffix, e.g. "(RB – GB)". */
export function posTeam(pos: string | null, nflTeam: string | null): string {
  const parts = [pos, nflTeam].filter(Boolean)

  return parts.length > 0 ? `(${parts.join(' – ')})` : ''
}
