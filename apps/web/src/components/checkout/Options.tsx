"use client";

import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow } from "@/lib/pricing";
import type { Coupon, Totals } from "./cart-math";
import { addDays } from "./cart-math";
import { useT } from "@/i18n/client";
import { hasKey, type MsgKey, type TFn, type Translator } from "@/i18n/core";

export const PAYMENTS = [
  { id: "bkash", label: "bKash", icon: "wallet", desc: "pay.bkashDesc" },
  { id: "nagad", label: "Nagad", icon: "phone", desc: "pay.nagadDesc" },
  { id: "card", label: "pay.cardLabel", icon: "card", desc: "pay.cardDesc" },
  { id: "half", label: "pay.halfLabel", icon: "cash", desc: "pay.halfDesc" },
] as const satisfies readonly { id: string; label: string; icon: string; desc: MsgKey }[];

export type PaymentId = (typeof PAYMENTS)[number]["id"];

/** Brand names (bKash, Nagad) stay as they are; the other labels are dictionary keys. */
export function payText(s: string, t: TFn) {
  return hasKey(s) ? t(s) : s;
}

export function paymentLabel(id: PaymentId, total: number, tr: Translator) {
  if (id === "half") return tr.t("pay.halfSummary", { now: tr.bdt(Math.ceil(total / 2)), later: tr.bdt(total - Math.ceil(total / 2)) });
  return payText(PAYMENTS.find((p) => p.id === id)!.label, tr.t);
}

export function DeliveryStep({ t, coupon }: { t: Totals; coupon: Coupon | null }) {
  const w = deliveryWindow();
  const now = new Date().toISOString();
  const { t: tr, bdt, lang } = useT();
  return (
    <div className="space-y-3">
      <p className="text-sm text-ink-2">
        {tr("del.intro")}
      </p>
      <ul aria-label={tr("del.perStore")} className="grid gap-3">
        {t.groups.map((g) => {
          const free = g.shipping === 0 || coupon?.kind === "freeship";
          return (
            <li key={g.market} data-store={g.market} className="co-option is-active cursor-default">
              <span className="chip-icon is-active shrink-0">
                <Icon name="truck" size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-xs font-semibold text-accent">{tr("del.fromLine", { source: STORES[g.market].source, items: tr("cart.items", { count: g.count }) })}</span>
                <span className="mt-0.5 flex flex-wrap items-center gap-2 text-sm font-bold">
                  {tr("del.standard")}
                  <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                    <Icon name="check" size={11} stroke={3} /> {tr("del.selected")}
                  </span>
                </span>
                <span className="mt-1 flex items-center gap-1.5 text-xs text-ink-2">
                  <Icon name="clock" size={14} /> {tr("del.arrives", { from: addDays(now, w.min, lang), to: addDays(now, w.max, lang), min: w.min, max: w.max })}
                </span>
              </span>
              <span className="shrink-0 text-right">
                <span className={"block text-sm font-extrabold tabular-nums " + (free ? "text-good" : "")}>{free ? tr("common.free") : bdt(g.shipping)}</span>
                {free && g.shipping > 0 && <span className="block text-[11px] text-ink-3 line-through">{bdt(g.shipping)}</span>}
              </span>
            </li>
          );
        })}
      </ul>
      <ol className="mt-2 grid grid-cols-3 gap-2 text-center text-[11px] text-ink-2">
        {[
          ["bag", tr("del.bought"), tr("del.d13")],
["plane", tr("del.hubToBd"), tr("del.d35")],
["home", tr("del.toDoor"), tr("del.d13")],
        ].map(([i, a, b]) => (
          <li key={a} className="rounded-2xl bg-surface p-2.5 shadow-[var(--shadow-1)]">
            <Icon name={i} size={18} className="mx-auto text-accent" />
            <span className="mt-1 block font-semibold text-ink">{a}</span>
            {b}
          </li>
        ))}
      </ol>
    </div>
  );
}

export function PaymentStep({ value, onChange, total }: { value: PaymentId | null; onChange: (id: PaymentId) => void; total: number }) {
  const { t, bdt } = useT();
  return (
    <fieldset className="space-y-3">
      <legend className="sr-only">{t("pay.method")}</legend>
      <div className="grid gap-3" role="radiogroup" aria-label={t("pay.method")}>
        {PAYMENTS.map((p) => {
          const on = value === p.id;
          return (
            <label key={p.id} className={"co-option items-center " + (on ? "is-active" : "")}>
              <input type="radio" name="payment" className="sr-only" checked={on} onChange={() => onChange(p.id)} />
              <span className={"chip-icon shrink-0 " + (on ? "is-active" : "")}>
                <Icon name={p.icon} size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">{payText(p.label, t)}</span>
                <span className="block text-xs text-ink-2">
                  {p.id === "half" ? t("pay.halfNow", { now: bdt(Math.ceil(total / 2)), later: bdt(total - Math.ceil(total / 2)) }) : t(p.desc)}
                </span>
              </span>
              {p.id === "half" && <span className="rounded-full bg-warn/15 px-2 py-0.5 text-[10px] font-bold uppercase text-warn">{t("pay.demo")}</span>}
              <span className="co-radio" aria-hidden />
            </label>
          );
        })}
      </div>
      <p className="flex items-start gap-2 rounded-2xl bg-surface-2 p-3 text-xs text-ink-2">
        <Icon name="info" size={15} className="mt-px shrink-0" />
        {t("pay.preview")}
      </p>
    </fieldset>
  );
}
