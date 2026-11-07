"use client";

import { useT } from "@/i18n/client";
import { useEffect, useState } from "react";

const pad = (n: number) => String(n).padStart(2, "0");

/** Big HH:MM:SS countdown. Each digit pair re-mounts on change so it drops in (CSS keyframe). Pauses while the tab is hidden. */
export function FlashCountdown({ to, mode }: { to: string; mode: "ends" | "starts" }) {
  const [ms, setMs] = useState<number | null>(null);
  const { t, digits } = useT();
  useEffect(() => {
    const target = Date.parse(to);
    const tick = () => setMs(Math.max(0, target - Date.now()));
    const first = setTimeout(tick, 0);
    const t = setInterval(() => {
      if (!document.hidden) tick();
    }, 1000);
    return () => {
      clearTimeout(first);
      clearInterval(t);
    };
  }, [to]);

  const label = mode === "ends" ? t("flash.endsIn") : t("flash.startsIn");
  if (ms === 0) {
    return <p className="text-sm font-bold text-ink-2">{mode === "ends" ? t("flash.ended") : t("flash.startingNow")}</p>;
  }
  const total = ms ?? 0;
  const d = Math.floor(total / 864e5);
  const h = Math.floor((total % 864e5) / 3.6e6);
  const m = Math.floor((total % 3.6e6) / 6e4);
  const s = Math.floor((total % 6e4) / 1000);
  const cells = d > 0 ? [[d, t("cd.daysUnit")], [h, t("cd.hrs")], [m, t("cd.min")]] : [[h, t("cd.hrs")], [m, t("cd.min")], [s, t("cd.sec")]];

  return (
    <div className="flex flex-col items-start gap-1 sm:items-end">
      <span className="text-[11px] font-bold uppercase tracking-wider text-ink-2">{label}</span>
      <div
        className="fs-digits text-2xl sm:text-3xl"
        role="timer"
        aria-live="off"
        aria-label={ms === null ? label : t("flash.aria", { label, d: d ? t("flash.ariaDays", { d }) : "", h, m })}
      >
        {cells.map(([v, unit], i) => (
          <span key={unit} className="contents">
            {i > 0 && (
              <span className="fs-sep" aria-hidden>
                :
              </span>
            )}
            <span className="fs-cell" aria-hidden>
              <span key={ms === null ? "x" : v} className="fs-tick">
                {ms === null ? "--" : digits(pad(v as number))}
              </span>
              <small>{unit}</small>
            </span>
          </span>
        ))}
      </div>
    </div>
  );
}
