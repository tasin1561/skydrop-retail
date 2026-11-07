"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { formatBdt } from "@/lib/pricing";

const BITS = [
  [-26, -26, "var(--good)"],
  [-8, -34, "var(--brand-2)"],
  [14, -30, "var(--accent)"],
  [30, -20, "var(--accent-2)"],
  [-36, -10, "var(--brand-2)"],
  [40, -6, "var(--good)"],
] as const;

/**
 * "৳X more for free Skydrop delivery" meter. The fill eases to the new level (transform only); the
 * moment it reaches 100% in this session a tiny confetti pop plays once. Loaded already full: no party.
 */
export function FreeShipMeter({ sub, threshold, source }: { sub: number; threshold: number; source: string }) {
  const full = sub >= threshold;
  const toFree = Math.max(0, threshold - sub);
  const pct = Math.min(1, sub / threshold);
  // "adjust state during render": remember the last fullness to detect the crossing
  const [prev, setPrev] = useState(full);
  const [party, setParty] = useState(0);
  if (prev !== full) {
    setPrev(full);
    if (full) setParty((n) => n + 1);
  }
  return (
    <div className="mo-free space-y-2" data-full={full}>
      <div
        className="co-track"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={threshold}
        aria-valuenow={Math.min(sub, threshold)}
        aria-label={`Free Skydrop delivery progress for ${source}`}
      >
        <span style={{ transform: `scaleX(${pct})` }} />
      </div>
      {party > 0 && full && (
        <span key={party} className="mo-confetti" aria-hidden="true">
          {BITS.map(([x, y, c], n) => (
            <i key={n} style={{ ["--x" as string]: `${x}px`, ["--y" as string]: `${y}px`, ["--c" as string]: c, ["--n" as string]: n }} />
          ))}
        </span>
      )}
      <p className="text-xs text-ink-2" aria-live="polite">
        {toFree > 0 ? (
          <>
            <b className="text-ink">{formatBdt(toFree)}</b> more from {source} for free Skydrop delivery
          </>
        ) : (
          <span key={party} className={"inline-flex items-center gap-1 font-semibold text-good " + (party ? "mo-saved" : "")}>
            <Icon name="check" size={14} stroke={2.6} /> Free Skydrop delivery unlocked for this store
          </span>
        )}
      </p>
    </div>
  );
}
