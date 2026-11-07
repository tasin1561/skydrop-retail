"use client";

import "./cms.css";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import Link from "next/link";
import type { Product } from "@/lib/types";
import { flashOffBdt, formatBdt } from "@/lib/pricing";
import { restrictionOf } from "@/lib/restrictions";
import { Icon } from "@/components/Icon";
import { Countdown } from "@/components/offers/Countdown";

/** Product page: live flash-sale panel (price already includes it). */
export function FlashNotice({ p }: { p: Product }) {
  const off = flashOffBdt(p);
  const { t, bdt } = useT();
  if (!off || !p.flashSale) return null;
  return (
    <div className="rx-note mt-3 flex items-center gap-3 rounded-2xl bg-[color-mix(in_srgb,var(--bad)_10%,var(--surface))] p-3 ring-1 ring-[color-mix(in_srgb,var(--bad)_25%,transparent)]">
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-bad text-white">
        <Icon name="bolt" size={20} stroke={2.4} className="fs-bolt" />
      </span>
      <div className="min-w-0 flex-1 text-sm">
        <p className="font-extrabold text-ink">
          {t("flash.extraOff", { title: p.flashSale.title || t("flash.title"), amount: bdt(off) })}
        </p>
        <p className="text-xs text-ink-2">
          {t("flash.inPrice")} <Countdown endsAt={p.flashSale.endsAt} />
          {p.flashSale.maxQty ? t("flash.perOrder", { count: p.flashSale.maxQty }) : ""}
        </p>
      </div>
    </div>
  );
}

/** Product page: "can this ship to Bangladesh?" notice. Blocked items also get a disabled buy box. */
export function RestrictionNotice({ p }: { p: Product }) {
  const r = restrictionOf(p);
  const { t, bdt } = useT();
  if (!r) return null;
  const block = r.action === "block";
  return (
    <div
      role={block ? "alert" : "note"}
      className={
        "rx-note mt-3 flex items-start gap-3 rounded-2xl p-3 text-sm " +
        (block
          ? "bg-[color-mix(in_srgb,var(--bad)_10%,var(--surface))] ring-1 ring-[color-mix(in_srgb,var(--bad)_30%,transparent)]"
          : "bg-[color-mix(in_srgb,var(--warn)_10%,var(--surface))] ring-1 ring-[color-mix(in_srgb,var(--warn)_28%,transparent)]")
      }
    >
      <span className={"grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white " + (block ? "bg-bad" : "bg-warn")}>
        <Icon name={block ? "x" : "info"} size={18} stroke={2.4} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="font-extrabold text-ink">
          {block ? t("restrict.cantShip") : r.action === "surcharge" ? t("restrict.includes", { amount: bdt(r.surchargeBdt ?? 0) }) : t("restrict.before")}
        </p>
        <p className="mt-0.5 text-xs leading-relaxed text-ink-2">
          {r.message}{" "}
          <Link href="/legal/shipping#restricted" className="font-semibold text-accent underline-offset-2 hover:underline">
            {t("restrict.whatShip")}
          </Link>
        </p>
      </div>
    </div>
  );
}

export function isBlocked(p: Product) {
  return restrictionOf(p)?.action === "block";
}
