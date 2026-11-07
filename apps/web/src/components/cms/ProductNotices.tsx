import "./cms.css";
import Link from "next/link";
import type { Product } from "@/lib/types";
import { flashOffBdt, formatBdt } from "@/lib/pricing";
import { restrictionOf } from "@/lib/restrictions";
import { Icon } from "@/components/Icon";
import { Countdown } from "@/components/offers/Countdown";

/** Product page: live flash-sale panel (price already includes it). */
export function FlashNotice({ p }: { p: Product }) {
  const off = flashOffBdt(p);
  if (!off || !p.flashSale) return null;
  return (
    <div className="rx-note mt-3 flex items-center gap-3 rounded-2xl bg-[color-mix(in_srgb,var(--bad)_10%,var(--surface))] p-3 ring-1 ring-[color-mix(in_srgb,var(--bad)_25%,transparent)]">
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-bad text-white">
        <Icon name="bolt" size={20} stroke={2.4} className="fs-bolt" />
      </span>
      <div className="min-w-0 flex-1 text-sm">
        <p className="font-extrabold text-ink">
          {p.flashSale.title || "Flash sale"}: extra {formatBdt(off)} off
        </p>
        <p className="text-xs text-ink-2">
          Already in the price above. <Countdown endsAt={p.flashSale.endsAt} />
          {p.flashSale.maxQty ? ` · up to ${p.flashSale.maxQty} per order` : ""}
        </p>
      </div>
    </div>
  );
}

/** Product page: "can this ship to Bangladesh?" notice. Blocked items also get a disabled buy box. */
export function RestrictionNotice({ p }: { p: Product }) {
  const r = restrictionOf(p);
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
          {block ? "We can't ship this to Bangladesh" : r.action === "surcharge" ? `Includes ${formatBdt(r.surchargeBdt ?? 0)} special handling` : "Before you order"}
        </p>
        <p className="mt-0.5 text-xs leading-relaxed text-ink-2">
          {r.message}{" "}
          <Link href="/legal/shipping#restricted" className="font-semibold text-accent underline-offset-2 hover:underline">
            What we can ship
          </Link>
        </p>
      </div>
    </div>
  );
}

export function isBlocked(p: Product) {
  return restrictionOf(p)?.action === "block";
}
