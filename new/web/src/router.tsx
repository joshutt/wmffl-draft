import { useEffect, useState, type MouseEvent, type ReactNode } from 'react'

// Two-route SPA (board at /, commish console at /commish) — a hand-rolled
// pathname router keeps the dependency list at react + react-dom, matching
// the no-framework posture of the API. Production .htaccess falls back to
// index.html so deep links work.

const listeners = new Set<() => void>()

export function navigate(path: string): void {
  history.pushState(null, '', path)
  listeners.forEach((fn) => fn())
}

export function usePath(): string {
  const [path, setPath] = useState(window.location.pathname)

  useEffect(() => {
    const update = () => setPath(window.location.pathname)
    listeners.add(update)
    window.addEventListener('popstate', update)

    return () => {
      listeners.delete(update)
      window.removeEventListener('popstate', update)
    }
  }, [])

  return path
}

export function Link({ to, className, children }: { to: string; className?: string; children: ReactNode }) {
  const onClick = (e: MouseEvent<HTMLAnchorElement>) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return
    }
    e.preventDefault()
    navigate(to)
  }

  return (
    <a href={to} className={className} onClick={onClick}>
      {children}
    </a>
  )
}
