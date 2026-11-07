"use client";

import { useEffect, useState } from "react";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const TIPS: MsgKey[] = ["ws.t1", "ws.t2", "ws.t3", "ws.t4", "ws.t5", "ws.t6"];

/**
 * A calm progress indication while the shopper waits: the India → Kolkata → Bangladesh route with a
 * single line that fills slowly once (no loop, no moving parcel), and a tip that changes every 7s with
 * a plain fade. Reduced-motion users get a static, half-filled route.
 */
export function WaitStory() {
  const [tip, setTip] = useState(0);
  const { t } = useT();
  useEffect(() => {
    const t = setInterval(() => !document.hidden && setTip((i) => (i + 1) % TIPS.length), 7000);
    return () => clearInterval(t);
  }, []);
  return (
    <div className="wait-story card overflow-hidden p-4" aria-hidden="false">
      <svg viewBox="0 0 320 90" className="h-auto w-full" role="img" aria-label={t("ws.aria")}>
        <path id="ws-route" d="M28 64 C 90 10, 140 10, 160 42 S 250 80, 292 30" className="ws-route" fill="none" />
        <path d="M28 64 C 90 10, 140 10, 160 42 S 250 80, 292 30" className="ws-route-done" fill="none" pathLength={100} />
        {(
          [
            [28, 64, t("ws.store"), "start", -10],
            [160, 42, t("ws.hub"), "middle", 0],
            [292, 30, t("ws.door"), "end", 10],
          ] as const
        ).map(([x, y, label, anchor, dx]) => (
          <g key={label}>
            <circle cx={x} cy={y} r="5" className="ws-stop" />
            <text x={x + dx} y={y + 18} textAnchor={anchor} className="ws-label">
              {label}
            </text>
          </g>
        ))}
      </svg>
      <p key={tip} className="ws-tip mt-2 min-h-10 text-center text-xs text-ink-2 sm:text-sm">
        <span className="font-bold text-ink">{t("ws.dyk")}</span>
        {t(TIPS[tip])}
      </p>
    </div>
  );
}
