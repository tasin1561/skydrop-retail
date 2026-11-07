"use client";

import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow, formatBdt } from "@/lib/pricing";
import type { Coupon, Totals } from "./cart-math";
import { BundleSavingRow } from "@/components/shopping/BundleSavingRow";
import { bundleOf } from "@/components/shopping/useBundleTotals";
import { CountUp } from "@/components/motion/CountUp";

// Order summary lines used by the cart side card, the checkout side card and the review step.
export function SummaryLines({ t, coupon, perStore }: { t: Totals; coupon: Coupon | null; perStore?: boolean }) {
  return (
    <dl className="space-y-2.5 text-sm">
      <div className="flex justify-between gap-3">
        <dt className="text-ink-2">
          Items ({t.count})
        </dt>
        <dd className="font-semibold tabular-nums">{formatBdt(t.subtotal)}</dd>
      </div>
      {perStore && t.groups.length > 1 ? (
        t.groups.map((g) => (
          <div key={g.market} className="flex justify-between gap-3">
            <dt className="text-ink-2">Shipping from {STORES[g.market].source}</dt>
            <dd className={"font-semibold tabular-nums " + (g.shipping === 0 ? "text-good" : "")}>
              {g.shipping === 0 ? "Free" : formatBdt(g.shipping)}
            </dd>
          </div>
        ))
      ) : (
        <div className="flex justify-between gap-3">
          <dt className="text-ink-2">
            Skydrop shipping{t.groups.length > 1 ? ` (${t.groups.length} shipments)` : ""}
          </dt>
          <dd className={"font-semibold tabular-nums " + (t.shipping === 0 ? "text-good" : "")}>
            {t.shipping === 0 ? "Free" : formatBdt(t.shipping)}
          </dd>
        </div>
      )}
      <BundleSavingRow t={t} />
      {t.discount - t.pointsDiscount - bundleOf(t) > 0 && (
        <div className="flex justify-between gap-3 text-good">
          <dt className="inline-flex items-center gap-1.5">
            <Icon name="tag" size={14} stroke={2} /> {coupon?.code ?? "Discount"}
          </dt>
          <dd key={t.discount} className="co-bump font-semibold tabular-nums">
            −{formatBdt(t.discount - t.pointsDiscount - bundleOf(t))}
          </dd>
        </div>
      )}
      {t.pointsDiscount > 0 && (
        <div className="flex justify-between gap-3 text-good">
          <dt className="inline-flex items-center gap-1.5">
            <Icon name="spark" size={14} stroke={2} /> {t.pointsRedeemed} Skydrop Points
          </dt>
          <dd key={t.pointsDiscount} className="co-bump font-semibold tabular-nums">
            −{formatBdt(t.pointsDiscount)}
          </dd>
        </div>
      )}
      {!!t.unboxingFee && (
        <div className="flex justify-between gap-3">
          <dt className="inline-flex items-center gap-1.5 text-ink-2">
            <Icon name="package" size={14} stroke={2} /> Unboxing check
          </dt>
          <dd key={t.unboxingFee} className="co-bump font-semibold tabular-nums">
            {formatBdt(t.unboxingFee)}
          </dd>
        </div>
      )}
      <div className="flex items-baseline justify-between gap-3 border-t border-dashed border-line pt-3">
        <dt className="font-bold">Total</dt>
        <dd className="text-xl font-extrabold tracking-tight tabular-nums">
          <CountUp value={t.total} />
        </dd>
      </div>
          {t.pointsToEarn > 0 && (
        <p className="flex items-center gap-1.5 text-xs font-semibold text-accent">
          <Icon name="spark" size={13} stroke={2} /> You&apos;ll earn {t.pointsToEarn} Skydrop Points when this is delivered
        </p>
      )}
    </dl>
  );
}

export function DeliveryPromise({ compact }: { compact?: boolean }) {
  const w = deliveryWindow();
  return (
    <div className={"flex items-start gap-3 rounded-2xl bg-surface-2 " + (compact ? "p-2.5" : "p-3")}>
      <span className="chip-icon shrink-0">
        <Icon name="truck" size={18} />
      </span>
      <p className="text-xs leading-relaxed text-ink-2">
        <span className="block text-sm font-bold text-ink">
          Door delivery in {w.min}-{w.max} days
        </span>
        We buy in India, Skydrop carries it via the Kolkata hub to your door in Bangladesh.
      </p>
    </div>
  );
}

export function TrustRow() {
  return (
    <ul className="grid gap-2 text-xs text-ink-2">
      {[
        ["shield", "Live price and stock check before you pay"],
        ["refresh", "Full refund if we can't buy your item"],
      ].map(([i, t]) => (
        <li key={t} className="flex items-center gap-2">
          <Icon name={i} size={15} className="text-good" /> {t}
        </li>
      ))}
    </ul>
  );
}
