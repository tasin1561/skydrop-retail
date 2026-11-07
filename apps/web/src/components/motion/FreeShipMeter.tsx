"use client";

import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";


/**
 * "৳X more for free Skydrop delivery" meter. The fill eases to the new level (transform only); reaching
 * 100% swaps the line for a quiet "unlocked" check (no celebration).
 */
export function FreeShipMeter({ sub, threshold, source }: { sub: number; threshold: number; source: string }) {
  const full = sub >= threshold;
  const { t, bdt } = useT();
  const toFree = Math.max(0, threshold - sub);
  const pct = Math.min(1, sub / threshold);
  return (
    <div className="mo-free space-y-2" data-full={full}>
      <div
        className="co-track"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={threshold}
        aria-valuenow={Math.min(sub, threshold)}
        aria-label={t("ship.progress", { source })}
      >
        <span style={{ transform: `scaleX(${pct})` }} />
      </div>
      <p className="text-xs text-ink-2" aria-live="polite">
        {toFree > 0 ? (
          <>
            {rich(t("ship.more", { source }), { amount: <b className="text-ink">{bdt(toFree)}</b> })}
          </>
        ) : (
          <span className="mo-saved inline-flex items-center gap-1 font-semibold text-good">
            <Icon name="check" size={14} stroke={2.6} /> {t("ship.unlocked")}
          </span>
        )}
      </p>
    </div>
  );
}
