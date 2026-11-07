"use client";

import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow } from "@/lib/pricing";
import type { Coupon, Totals } from "./cart-math";
import { BundleSavingRow } from "@/components/shopping/BundleSavingRow";
import { bundleOf } from "@/components/shopping/useBundleTotals";
import { CountUp } from "@/components/motion/CountUp";
import { useT } from "@/i18n/client";

// Order summary lines used by the cart side card, the checkout side card and the review step.
export function SummaryLines({ t, coupon, perStore }: { t: Totals; coupon: Coupon | null; perStore?: boolean }) {
  const { t: tr, bdt } = useT();
  return (
    <dl className="space-y-2.5 text-sm">
      <div className="flex justify-between gap-3">
        <dt className="text-ink-2">
          {tr("sum.items", { count: t.count })}
        </dt>
        <dd className="font-semibold tabular-nums">{bdt(t.subtotal)}</dd>
      </div>
      {perStore && t.groups.length > 1 ? (
        t.groups.map((g) => (
          <div key={g.market} className="flex justify-between gap-3">
            <dt className="text-ink-2">{tr("sum.shipFrom", { source: STORES[g.market].source })}</dt>
            <dd className={"font-semibold tabular-nums " + (g.shipping === 0 ? "text-good" : "")}>
              {g.shipping === 0 ? tr("common.free") : bdt(g.shipping)}
            </dd>
          </div>
        ))
      ) : (
        <div className="flex justify-between gap-3">
          <dt className="text-ink-2">
            {tr("sum.shipping")}{t.groups.length > 1 ? tr("sum.shipments", { count: t.groups.length }) : ""}
          </dt>
          <dd className={"font-semibold tabular-nums " + (t.shipping === 0 ? "text-good" : "")}>
            {t.shipping === 0 ? tr("common.free") : bdt(t.shipping)}
          </dd>
        </div>
      )}
      <BundleSavingRow t={t} />
      {t.discount - t.pointsDiscount - bundleOf(t) > 0 && (
        <div className="flex justify-between gap-3 text-good">
          <dt className="inline-flex items-center gap-1.5">
            <Icon name="tag" size={14} stroke={2} /> {coupon?.code ?? tr("sum.discount")}
          </dt>
          <dd key={t.discount} className="co-bump font-semibold tabular-nums">
            −{bdt(t.discount - t.pointsDiscount - bundleOf(t))}
          </dd>
        </div>
      )}
      {t.pointsDiscount > 0 && (
        <div className="flex justify-between gap-3 text-good">
          <dt className="inline-flex items-center gap-1.5">
            <Icon name="spark" size={14} stroke={2} /> {tr("sum.points", { count: t.pointsRedeemed })}
          </dt>
          <dd key={t.pointsDiscount} className="co-bump font-semibold tabular-nums">
            −{bdt(t.pointsDiscount)}
          </dd>
        </div>
      )}
      {!!t.unboxingFee && (
        <div className="flex justify-between gap-3">
          <dt className="inline-flex items-center gap-1.5 text-ink-2">
            <Icon name="package" size={14} stroke={2} /> {tr("sum.unboxing")}
          </dt>
          <dd key={t.unboxingFee} className="co-bump font-semibold tabular-nums">
            {bdt(t.unboxingFee)}
          </dd>
        </div>
      )}
      <div className="flex items-baseline justify-between gap-3 border-t border-dashed border-line pt-3">
        <dt className="font-bold">{tr("sum.total")}</dt>
        <dd className="text-xl font-extrabold tracking-tight tabular-nums">
          <CountUp value={t.total} />
        </dd>
      </div>
          {t.pointsToEarn > 0 && (
        <p className="flex items-center gap-1.5 text-xs font-semibold text-accent">
          <Icon name="spark" size={13} stroke={2} /> {tr("sum.earn", { count: t.pointsToEarn })}
        </p>
      )}
    </dl>
  );
}

export function DeliveryPromise({ compact }: { compact?: boolean }) {
  const w = deliveryWindow();
  const { t } = useT();
  return (
    <div className={"flex items-start gap-3 rounded-2xl bg-surface-2 " + (compact ? "p-2.5" : "p-3")}>
      <span className="chip-icon shrink-0">
        <Icon name="truck" size={18} />
      </span>
      <p className="text-xs leading-relaxed text-ink-2">
        <span className="block text-sm font-bold text-ink">
          {t("sum.door", { min: w.min, max: w.max })}
        </span>
        {t("sum.doorBody")}
      </p>
    </div>
  );
}

export function TrustRow() {
  const { t } = useT();
  return (
    <ul className="grid gap-2 text-xs text-ink-2">
      {[
        ["shield", t("sum.trust1")],
["refresh", t("sum.trust2")],
      ].map(([i, t]) => (
        <li key={t} className="flex items-center gap-2">
          <Icon name={i} size={15} className="text-good" /> {t}
        </li>
      ))}
    </ul>
  );
}
