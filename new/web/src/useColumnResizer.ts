import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type KeyboardEvent as ReactKeyboardEvent,
  type PointerEvent as ReactPointerEvent,
} from 'react'

// Drives one draggable column divider. Width is a pixel value the user has
// dragged to, persisted per browser in localStorage; `width === null` means
// "no manual override yet", so the caller's CSS falls back to its own
// flexible default sizing.

function clamp(n: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, n))
}

function readStored(key: string, min: number, max: number): number | null {
  try {
    const raw = localStorage.getItem(key)
    if (raw === null) {
      return null
    }
    const n = Number(raw)
    return Number.isFinite(n) ? clamp(n, min, max) : null
  } catch {
    return null
  }
}

export interface ColumnResizerHandlers {
  onPointerDown: (e: ReactPointerEvent<HTMLDivElement>) => void
  onPointerMove: (e: ReactPointerEvent<HTMLDivElement>) => void
  onPointerUp: (e: ReactPointerEvent<HTMLDivElement>) => void
  onPointerCancel: (e: ReactPointerEvent<HTMLDivElement>) => void
  onKeyDown: (e: ReactKeyboardEvent<HTMLDivElement>) => void
  onDoubleClick: () => void
}

export function useColumnResizer(storageKey: string, min: number, max: number) {
  const [width, setWidth] = useState<number | null>(() => readStored(storageKey, min, max))
  const widthRef = useRef(width)
  const dragRef = useRef<{ startX: number; startWidth: number } | null>(null)

  useEffect(() => {
    widthRef.current = width
  }, [width])

  const persist = useCallback(() => {
    try {
      if (widthRef.current !== null) {
        localStorage.setItem(storageKey, String(widthRef.current))
      }
    } catch {
      // private mode / storage disabled — the drag still works for this session
    }
  }, [storageKey])

  const onPointerDown = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      // No manual width yet: seed the drag from the rendered width of the
      // column this handle sits right after (grid auto-placement keeps DOM
      // order == column order, so previousElementSibling is that column).
      const rendered = e.currentTarget.previousElementSibling?.getBoundingClientRect().width
      const current = widthRef.current ?? rendered ?? min
      dragRef.current = { startX: e.clientX, startWidth: clamp(current, min, max) }
      e.currentTarget.setPointerCapture(e.pointerId)
      e.preventDefault()
    },
    [min, max],
  )

  const onPointerMove = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      if (dragRef.current === null) {
        return
      }
      const delta = e.clientX - dragRef.current.startX
      setWidth(clamp(dragRef.current.startWidth + delta, min, max))
    },
    [min, max],
  )

  const endDrag = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      if (dragRef.current === null) {
        return
      }
      dragRef.current = null
      e.currentTarget.releasePointerCapture(e.pointerId)
      persist()
    },
    [persist],
  )

  const onKeyDown = useCallback(
    (e: ReactKeyboardEvent<HTMLDivElement>) => {
      const step = e.shiftKey ? 48 : 16
      let next: number | null = null
      if (e.key === 'ArrowLeft') {
        next = clamp((widthRef.current ?? min) - step, min, max)
      } else if (e.key === 'ArrowRight') {
        next = clamp((widthRef.current ?? min) + step, min, max)
      } else if (e.key === 'Home') {
        next = min
      } else if (e.key === 'End') {
        next = max
      }
      if (next !== null) {
        e.preventDefault()
        widthRef.current = next
        setWidth(next)
        persist()
      }
    },
    [min, max, persist],
  )

  const onDoubleClick = useCallback(() => {
    widthRef.current = null
    setWidth(null)
    try {
      localStorage.removeItem(storageKey)
    } catch {
      // ignore
    }
  }, [storageKey])

  const handlers: ColumnResizerHandlers = {
    onPointerDown,
    onPointerMove,
    onPointerUp: endDrag,
    onPointerCancel: endDrag,
    onKeyDown,
    onDoubleClick,
  }

  return { width, min, max, handlers }
}
