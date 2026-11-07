"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

// Pill stepper 1-10 with a rolling number (direction follows the change).
export function QtyStepper({
  value,
  onChange,
  min = 1,
  max = 10,
  compact,
}: {
  value: number;
  onChange: (n: number) => void;
  min?: number;
  max?: number;
  compact?: boolean;
}) {
  // Track the last value in state (React's "adjust state while rendering" pattern) to pick the roll direction.
  const [last, setLast] = useState(value);
  const { t, digits } = useT();
  const [dir, setDir] = useState<"up" | "down">("up");
  if (value !== last) {
    setDir(value > last ? "up" : "down");
    setLast(value);
  }
  const size = compact ? "h-11 w-10" : "h-12 w-12";
  return (
    <div
      role="group"
      aria-label={t("qty.label")}
      className="inline-flex items-center rounded-full bg-surface-2 p-0.5 shadow-[inset_0_1px_2px_rgb(15_23_42/0.06)]"
      onKeyDown={(e) => {
        if (e.key === "ArrowUp" || e.key === "ArrowRight") {
          e.preventDefault();
          onChange(Math.min(max, value + 1));
        } else if (e.key === "ArrowDown" || e.key === "ArrowLeft") {
          e.preventDefault();
          onChange(Math.max(min, value - 1));
        }
      }}
    >
      <button
        type="button"
        aria-label={t("qty.dec")}
        disabled={value <= min}
        onClick={() => onChange(Math.max(min, value - 1))}
        className={size + " grid place-items-center rounded-full text-ink transition hover:bg-surface hover:text-accent hover:shadow-[var(--shadow-1)] active:scale-90 disabled:opacity-35"}
      >
        <Icon name="minus" size={18} stroke={2.2} />
      </button>
      <span className="relative grid h-8 w-8 place-items-center overflow-hidden" aria-live="polite" aria-atomic>
        <span key={value} className={(dir === "up" ? "pd-num-up" : "pd-num-down") + " text-base font-extrabold tabular-nums"}>
          {digits(value)}
        </span>
        <span className="sr-only">{t("qty.items")}</span>
      </span>
      <button
        type="button"
        aria-label={t("qty.inc")}
        disabled={value >= max}
        onClick={() => onChange(Math.min(max, value + 1))}
        className={size + " grid place-items-center rounded-full text-ink transition hover:bg-surface hover:text-accent hover:shadow-[var(--shadow-1)] active:scale-90 disabled:opacity-35"}
      >
        <Icon name="plus" size={18} stroke={2.2} />
      </button>
    </div>
  );
}
