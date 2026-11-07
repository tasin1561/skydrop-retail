"use client";

import { useEffect, useState } from "react";
import { useT } from "@/i18n/client";

function left(endsAt: string) {
  return Math.max(0, Date.parse(endsAt) - Date.now());
}

/** Live "ends in" timer for deals. Pauses while the tab is hidden. */
export function Countdown({ endsAt, compact }: { endsAt: string; compact?: boolean }) {
  const [ms, setMs] = useState<number | null>(null);
  const { t, digits } = useT();
  useEffect(() => {
    const tick = () => setMs(left(endsAt));
    tick();
    const t = setInterval(() => {
      if (!document.hidden) tick();
    }, 1000);
    return () => clearInterval(t);
  }, [endsAt]);
  if (ms === null) return null;
  if (ms === 0) return <span className="font-bold">{t("cd.ended")}</span>;
  const h = Math.floor(ms / 3.6e6);
  const m = Math.floor((ms % 3.6e6) / 6e4);
  const s = Math.floor((ms % 6e4) / 1000);
  const pad = (n: number) => String(n).padStart(2, "0");
  const text = h >= 48 ? t("cd.days", { count: Math.floor(h / 24) }) : digits(`${pad(h)}:${pad(m)}:${pad(s)}`);
  return (
    <span className="tabular-nums" aria-label={t("cd.endsInAria", { h, m })}>
      {compact ? text : t("cd.endsIn", { time: text })}
    </span>
  );
}
