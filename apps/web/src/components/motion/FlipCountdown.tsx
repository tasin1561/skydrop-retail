"use client";

import { useEffect, useState } from "react";

const pad = (n: number) => String(n).padStart(2, "0");

/**
 * HH:MM:SS with flip-in digits (each changed pair re-mounts and rotates in on X: transform + opacity).
 * Ticks once a second, skips while the tab is hidden, calls onEnd once at zero.
 * Screen readers get a minute-precision label, not a per-second stream.
 */
export function FlipCountdown({ to, label, onEnd }: { to: number; label: string; onEnd?: () => void }) {
  const [ms, setMs] = useState<number | null>(null);
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
    [h, "hrs"],
    [m, "min"],
    [s, "sec"],
  ];
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-[11px] font-bold uppercase tracking-wider text-ink-2">{label}</span>
      <div className="dd-digits" role="timer" aria-live="off" aria-label={ms === null ? label : `${label} ${h} hours ${m} minutes`}>
        {cells.map(([v, unit], i) => (
          <span key={unit} className="contents">
            {i > 0 && (
              <span className="dd-sep" aria-hidden>
                :
              </span>
            )}
            <span className="dd-cell" aria-hidden>
              <span className="dd-flip">
                <span key={ms === null ? "x" : v} className={ms === null ? "" : "in"}>
                  {ms === null ? "--" : pad(v)}
                </span>
              </span>
              <small>{unit}</small>
            </span>
          </span>
        ))}
      </div>
    </div>
  );
}
