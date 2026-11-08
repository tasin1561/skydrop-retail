"use client";

import { useT } from "@/i18n/client";
import { useEffect, useState } from "react";

const pad = (n: number) => String(n).padStart(2, "0");

/**
 * HH:MM:SS countdown; digits update in place (no flip animation).
 * Ticks once a second, skips while the tab is hidden, calls onEnd once at zero.
 * Screen readers get a minute-precision label, not a per-second stream.
 */
export function FlipCountdown({ to, label, onEnd }: { to: number; label: string; onEnd?: () => void }) {
  const [ms, setMs] = useState<number | null>(null);
  const { t, digits } = useT();
  useEffect(() => {
    let ended = false;
    const tick = () => {
      const left = Math.max(0, to - Date.now());
      setMs(left);
      if (left === 0 && !ended) {
        ended = true;
        onEnd?.();
      }
    };
    const first = setTimeout(tick, 0);
    const t = setInterval(() => {
      if (!document.hidden) tick();
    }, 1000);
    return () => {
      clearTimeout(first);
      clearInterval(t);
    };
  }, [to, onEnd]);

  const total = ms ?? 0;
  const h = Math.floor(total / 3.6e6);
  const m = Math.floor((total % 3.6e6) / 6e4);
  const s = Math.floor((total % 6e4) / 1000);
  const cells: [number, string][] = [
    [h, t("cd.hrs")],
    [m, t("cd.min")],
    [s, t("cd.sec")],
  ];
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs text-ink-3">{label}</span>
      <div className="dd-digits" role="timer" aria-live="off" aria-label={ms === null ? label : t("cd.aria", { label, h, m })}>
        {cells.map(([v, unit], i) => (
          <span key={unit} className="contents">
            {i > 0 && (
              <span className="dd-sep" aria-hidden>
                :
              </span>
            )}
            <span className="dd-cell" aria-hidden>
              <span className="dd-flip">
                <span>{ms === null ? "--" : digits(pad(v))}</span>
              </span>
              <small>{unit}</small>
            </span>
          </span>
        ))}
      </div>
    </div>
  );
}
