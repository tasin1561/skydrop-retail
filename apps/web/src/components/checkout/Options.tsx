"use client";

import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow, formatBdt } from "@/lib/pricing";
import type { Coupon, Totals } from "./cart-math";
import { addDays } from "./cart-math";

export const PAYMENTS = [
  { id: "bkash", label: "bKash", icon: "wallet", desc: "Pay from your bKash account" },
  { id: "nagad", label: "Nagad", icon: "phone", desc: "Pay from your Nagad account" },
  { id: "card", label: "Card (SSLCommerz)", icon: "card", desc: "Visa, Mastercard, Amex via SSLCommerz" },
  { id: "half", label: "Pay 50% advance, rest on delivery", icon: "cash", desc: "Half now, the rest to the Skydrop rider" },
] as const;

export type PaymentId = (typeof PAYMENTS)[number]["id"];

export function paymentLabel(id: PaymentId, total: number) {
  if (id === "half") return `50% advance (${formatBdt(Math.ceil(total / 2))}), ${formatBdt(total - Math.ceil(total / 2))} on delivery`;
  return PAYMENTS.find((p) => p.id === id)!.label;
}

export function DeliveryStep({ t, coupon }: { t: Totals; coupon: Coupon | null }) {
  const w = deliveryWindow();
  const now = new Date().toISOString();
  return (
    <div className="space-y-3">
      <p className="text-sm text-ink-2">
        Each store ships as its own Skydrop parcel. Items from different stores are bought separately in India and can arrive on different days.
      </p>
      <ul aria-label="Delivery method per store" className="grid gap-3">
        {t.groups.map((g) => {
          const free = g.shipping === 0 || coupon?.kind === "freeship";
          return (
            <li key={g.market} data-store={g.market} className="co-option is-active cursor-default">
              <span className="chip-icon is-active shrink-0">
                <Icon name="truck" size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-xs font-semibold text-accent">From {STORES[g.market].source}, India · {g.count} item{g.count === 1 ? "" : "s"}</span>
                <span className="mt-0.5 flex flex-wrap items-center gap-2 text-sm font-bold">
                  Skydrop door delivery · Standard
                  <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                    <Icon name="check" size={11} stroke={3} /> Selected
                  </span>
                </span>
                <span className="mt-1 flex items-center gap-1.5 text-xs text-ink-2">
                  <Icon name="clock" size={14} /> Arrives {addDays(now, w.min)} – {addDays(now, w.max)} ({w.min}-{w.max} days)
                </span>
              </span>
              <span className="shrink-0 text-right">
                <span className={"block text-sm font-extrabold tabular-nums " + (free ? "text-good" : "")}>{free ? "Free" : formatBdt(g.shipping)}</span>
                {free && g.shipping > 0 && <span className="block text-[11px] text-ink-3 line-through">{formatBdt(g.shipping)}</span>}
              </span>
            </li>
          );
        })}
      </ul>
      <ol className="mt-2 grid grid-cols-3 gap-2 text-center text-[11px] text-ink-2">
        {[
          ["bag", "Bought in India", "1-3 days"],
          ["plane", "Kolkata hub to Bangladesh", "3-5 days"],
          ["home", "To your door", "1-3 days"],
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
  return (
    <fieldset className="space-y-3">
      <legend className="sr-only">Payment method</legend>
      <div className="grid gap-3" role="radiogroup" aria-label="Payment method">
        {PAYMENTS.map((p) => {
          const on = value === p.id;
          return (
            <label key={p.id} className={"co-option items-center " + (on ? "is-active" : "")}>
              <input type="radio" name="payment" className="sr-only" checked={on} onChange={() => onChange(p.id)} />
              <span className={"chip-icon shrink-0 " + (on ? "is-active" : "")}>
                <Icon name={p.icon} size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">{p.label}</span>
                <span className="block text-xs text-ink-2">
                  {p.id === "half" ? `${formatBdt(Math.ceil(total / 2))} now, ${formatBdt(total - Math.ceil(total / 2))} on delivery` : p.desc}
                </span>
              </span>
              {p.id === "half" && <span className="rounded-full bg-warn/15 px-2 py-0.5 text-[10px] font-bold uppercase text-warn">Demo</span>}
              <span className="co-radio" aria-hidden />
            </label>
          );
        })}
      </div>
      <p className="flex items-start gap-2 rounded-2xl bg-surface-2 p-3 text-xs text-ink-2">
        <Icon name="info" size={15} className="mt-px shrink-0" />
        Preview store: payment gateways are not connected yet, so no money is charged when you place this order.
      </p>
    </fieldset>
  );
}
