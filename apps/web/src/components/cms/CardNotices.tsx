"use client";

import type { Product } from "@/lib/types";
import { useT } from "@/i18n/client";
import { flashOffBdt } from "@/lib/pricing";
import { restrictionOf } from "@/lib/restrictions";
import { Icon } from "@/components/Icon";
import { Countdown } from "@/components/offers/Countdown";

/** Product-card chips: live flash sale (with timer) and "can this ship to Bangladesh?" status. */
export function CardNotices({ p }: { p: Product }) {
  const off = flashOffBdt(p);
  const r = restrictionOf(p);
  const { t, bdt } = useT();
  if (!off && !r) return null;
  return (
    <div className="flex flex-wrap gap-1">
      {off > 0 && p.flashSale && (
        <span className="inline-flex w-fit items-center gap-1 rounded-md bg-bad px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-white">
          <Icon name="bolt" size={11} stroke={2.4} /> {t("notice.flash", { amount: bdt(off) })} · <Countdown endsAt={p.flashSale.endsAt} compact />
        </span>
      )}
      {r?.action === "block" && (
        <span title={r.message} className="inline-flex w-fit items-center gap-1 rounded-md bg-[color-mix(in_srgb,var(--bad)_12%,transparent)] px-1.5 py-0.5 text-[10px] font-bold text-bad">
          <Icon name="x" size={11} stroke={2.6} /> {t("notice.cantShip")}
        </span>
      )}
      {r?.action === "warn" && (
        <span title={r.message} className="inline-flex w-fit items-center gap-1 rounded-md bg-[color-mix(in_srgb,var(--warn)_14%,transparent)] px-1.5 py-0.5 text-[10px] font-bold text-warn">
          <Icon name="info" size={11} stroke={2.4} /> {t("notice.importNote")}
        </span>
      )}
      {r?.action === "surcharge" && (
        <span title={r.message} className="inline-flex w-fit items-center gap-1 rounded-md bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-2">
          <Icon name="package" size={11} stroke={2.2} /> {t("notice.handling", { amount: bdt(r.surchargeBdt ?? 0) })}
        </span>
      )}
    </div>
  );
}
