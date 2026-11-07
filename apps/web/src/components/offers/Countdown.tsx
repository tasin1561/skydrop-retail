"use client";

import { useEffect, useState } from "react";

function left(endsAt: string) {
  return Math.max(0, Date.parse(endsAt) - Date.now());
}

/** Live "ends in" timer for deals. Pauses while the tab is hidden. */
export function Countdown({ endsAt, compact }: { endsAt: string; compact?: boolean }) {
  const [ms, setMs] = useState<number | null>(null);
  useEffect(() => {
    const tick = () => setMs(left(endsAt));
    tick();
    const t = setInterval(() => {
      if (!document.hidden) tick();
    }, 1000);
    return () => clearInterval(t);
  }, [endsAt]);
  if (ms === null) return null;
  if (ms === 0) return <span className="font-bold">Deal ended</span>;
  const h = Math.floor(ms / 3.6e6);
  const m = Math.floor((ms % 3.6e6) / 6e4);
  const s = Math.floor((ms % 6e4) / 1000);
  const pad = (n: number) => String(n).padStart(2, "0");
  const text = h >= 48 ? `${Math.floor(h / 24)} days` : `${pad(h)}:${pad(m)}:${pad(s)}`;
  return (
    <span className="tabular-nums" aria-label={`Ends in ${h} hours ${m} minutes`}>
      {compact ? text : `Ends in ${text}`}
    </span>
  );
}
