import type { ColumnResizerHandlers } from '../useColumnResizer'

interface Props {
  label: string
  width: number | null
  min: number
  max: number
  handlers: ColumnResizerHandlers
}

/** A draggable divider between two grid columns — drag to resize, double-click to reset, arrow keys when focused. */
export function ColumnResizer({ label, width, min, max, handlers }: Props) {
  return (
    <div
      className="col-resizer"
      role="separator"
      aria-orientation="vertical"
      aria-label={label}
      aria-valuemin={min}
      aria-valuemax={max}
      aria-valuenow={width ?? undefined}
      tabIndex={0}
      {...handlers}
    >
      <span className="col-resizer-grip" aria-hidden="true" />
    </div>
  )
}
