"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { API_URL, apiEnabled } from "@/lib/apiClient";
import { PRICING } from "@/lib/pricing";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";
import { Icon } from "../Icon";
import { useFeature } from "../features/FeaturesProvider";

type Quote = {
  qty: number;
  weightKg: number;
  weightAssumed: boolean;
  unit: { storeBdt: number; importBdt: number; serviceBdt: number; totalBdt: number };
  itemsBdt: number;
  shippingBdt: number;
  totalBdt: number;
};

const CATS: { id: string; label: MsgKey; icon: string }[] = [
  { id: "fashion", label: "calc.cat.fashion", icon: "dress" },
  { id: "beauty", label: "calc.cat.beauty", icon: "lipstick" },
  { id: "electronics", label: "calc.cat.electronics", icon: "phone" },
  { id: "home", label: "calc.cat.home", icon: "home" },
  { id: "grocery", label: "calc.cat.grocery", icon: "basket" },
];

const up10 = (n: number) => Math.ceil(n / 10) * 10;

/** Same sums as the API (PricingService), with the bundled rates: used in demo mode and when the API is unreachable. */
function localQuote(inr: number, category: string, weight: number | null, qty: number): Quote {
  const base = inr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const imp = base * (PRICING.importPct[category] ?? 0.1);
  const total = up10((base + imp) * (1 + PRICING.margin));
  const kg = Math.max(0.5, (weight ?? PRICING.defaultWeightKg[category] ?? 0.5) * qty);
  const items = total * qty;
  const shipping = items >= PRICING.shipping.freeAboveBdt ? 0 : up10(PRICING.shipping.baseBdt + PRICING.shipping.perKgBdt * kg);
  return {
    qty,
    weightKg: weight ?? PRICING.defaultWeightKg[category] ?? 0.5,
    weightAssumed: weight === null,
    unit: { storeBdt: Math.round(base), importBdt: Math.round(imp), serviceBdt: Math.max(0, total - Math.round(base) - Math.round(imp)), totalBdt: total },
    itemsBdt: items,
    shippingBdt: shipping,
    totalBdt: items + shipping,
  };
}

/**
 * Cost calculator (like the shipping calculators on MoveOn and SkyBuy): type the price you see on the Indian store,
 * get what it costs delivered to your door in Bangladesh, line by line. Answers as you type.
 */
export function CostCalculator() {
  const { t, bdt, inr: fmtInr, digits } = useT();
  const [price, setPrice] = useState("");
  const [category, setCategory] = useState("fashion");
  const [weight, setWeight] = useState("");
  const [qty, setQty] = useState(1);
  const [quote, setQuote] = useState<Quote | null>(null);
  const [busy, setBusy] = useState(false);
  const requests = useFeature("product_requests");

  const inr = Number(price.replace(/[^\d.]/g, ""));
  const kg = weight.trim() ? Number(weight.replace(/[^\d.]/g, "")) : null;
  const valid = inr >= 1 && inr <= 2_000_000 && (kg === null || (kg >= 0.01 && kg <= 60));

  useEffect(() => {
    if (!valid) return;
    const ctrl = new AbortController();
    const id = setTimeout(async () => {
      setBusy(true);
      try {
        if (!apiEnabled) throw new Error("demo");
        const qs = new URLSearchParams({ inr: String(inr), category, qty: String(qty), ...(kg !== null ? { weight_kg: String(kg) } : {}) });
        const r = await fetch(`${API_URL}/pricing/quote?${qs}`, { headers: { Accept: "application/json" }, signal: ctrl.signal });
        if (!r.ok) throw new Error(String(r.status));
        setQuote((await r.json()) as Quote);
      } catch (e) {
        if ((e as Error).name !== "AbortError") setQuote(localQuote(inr, category, kg, qty));
      } finally {
        setBusy(false);
      }
    }, 250);
    return () => {
      clearTimeout(id);
      ctrl.abort();
    };
  }, [inr, category, kg, qty, valid]);

  const shown = valid ? quote : null;
  const field = "h-12 w-full rounded-xl bg-surface-2 px-3.5 text-[16px] font-semibold text-ink outline-none ring-1 ring-line focus:ring-2 focus:ring-accent";

  return (
    <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
      <form className="card flex flex-col gap-5 p-4 sm:p-5" onSubmit={(e) => e.preventDefault()}>
        <label className="block">
          <span className="mb-1.5 block text-sm font-bold">{t("calc.price")}</span>
          <span className="relative block">
            <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[16px] font-bold text-ink-3">₹</span>
            <input
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              inputMode="decimal"
              autoComplete="off"
              placeholder="1499"
              aria-describedby="calc-price-hint"
              className={field + " pl-8"}
            />
          </span>
          <span id="calc-price-hint" className="mt-1 block text-xs text-ink-2">
            {t("calc.priceHint")}
          </span>
        </label>

        <fieldset>
          <legend className="mb-1.5 text-sm font-bold">{t("calc.category")}</legend>
          <div className="flex flex-wrap gap-2">
            {CATS.map((c) => (
              <label
                key={c.id}
                className={
                  "inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold ring-1 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent " +
                  (category === c.id ? "bg-accent text-white ring-accent" : "bg-surface text-ink ring-line")
                }
              >
                <input type="radio" name="calc-cat" value={c.id} checked={category === c.id} onChange={() => setCategory(c.id)} className="sr-only" />
                <Icon name={c.icon} size={16} />
                {t(c.label)}
              </label>
            ))}
          </div>
        </fieldset>

        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="mb-1.5 block text-sm font-bold">{t("calc.weight")}</span>
            <input value={weight} onChange={(e) => setWeight(e.target.value)} inputMode="decimal" autoComplete="off" placeholder={t("calc.weightPh")} className={field} />
          </label>
          <div>
            <span className="mb-1.5 block text-sm font-bold" id="calc-qty">
              {t("calc.qty")}
            </span>
            <div role="group" aria-labelledby="calc-qty" className="flex h-12 items-center justify-between rounded-xl bg-surface-2 px-1 ring-1 ring-line">
              <button type="button" onClick={() => setQty((q) => Math.max(1, q - 1))} disabled={qty <= 1} aria-label={t("calc.less")} className="grid h-10 w-10 place-items-center rounded-lg text-ink disabled:opacity-35">
                <Icon name="minus" size={18} />
              </button>
              <span className="text-[16px] font-bold tabular-nums" aria-live="polite">
                {digits(qty)}
              </span>
              <button type="button" onClick={() => setQty((q) => Math.min(20, q + 1))} disabled={qty >= 20} aria-label={t("calc.more")} className="grid h-10 w-10 place-items-center rounded-lg text-ink disabled:opacity-35">
                <Icon name="plus" size={18} />
              </button>
            </div>
          </div>
        </div>
      </form>

      <section className="card flex flex-col p-4 sm:p-5" aria-live="polite" aria-busy={busy}>
        <h2 className="text-sm font-bold uppercase tracking-wider text-ink-3">{t("calc.result")}</h2>
        {shown ? (
          <>
            <p className="mt-2 text-[34px] font-extrabold leading-none tracking-tight tabular-nums">{bdt(shown.totalBdt)}</p>
            <p className="mt-1 text-sm text-ink-2">{t("calc.delivered", { inr: fmtInr(inr * shown.qty) })}</p>
            <dl className="mt-4 divide-y divide-line text-sm">
              {(
                [
                  ["calc.rowStore", shown.unit.storeBdt * shown.qty],
                  ["calc.rowImport", shown.unit.importBdt * shown.qty],
                  ["calc.rowService", shown.unit.serviceBdt * shown.qty],
                ] as [MsgKey, number][]
              ).map(([k, v]) => (
                <div key={k} className="flex items-center justify-between gap-3 py-2.5">
                  <dt className="text-ink-2">{t(k)}</dt>
                  <dd className="font-semibold tabular-nums">{bdt(v)}</dd>
                </div>
              ))}
              <div className="flex items-center justify-between gap-3 py-2.5">
                <dt className="text-ink-2">
                  {t("calc.rowDelivery")}
                  <span className="block text-xs text-ink-3">
                    {t(shown.weightAssumed ? "calc.weightAssumed" : "calc.weightGiven", { kg: digits(+(shown.weightKg * shown.qty).toFixed(2)) })}
                  </span>
                </dt>
                <dd className={"font-semibold tabular-nums " + (shown.shippingBdt === 0 ? "text-good" : "")}>{shown.shippingBdt === 0 ? t("calc.free") : bdt(shown.shippingBdt)}</dd>
              </div>
              <div className="flex items-center justify-between gap-3 py-3 text-base">
                <dt className="font-bold">{t("calc.rowTotal")}</dt>
                <dd className="font-extrabold tabular-nums">{bdt(shown.totalBdt)}</dd>
              </div>
            </dl>
            <p className="mt-1 text-xs leading-relaxed text-ink-2">{t("calc.note")}</p>
          </>
        ) : (
          <div className="grid flex-1 place-items-center py-8 text-center">
            <div>
              <span className="chip-icon mx-auto h-14 w-14 rounded-2xl">
                <Icon name="calc" size={26} />
              </span>
              <p className="mt-3 text-sm text-ink-2">{t("calc.empty")}</p>
            </div>
          </div>
        )}
        <div className="mt-auto grid gap-2 pt-4 sm:grid-cols-2">
          {requests && (
            <Link href="/request" className="btn btn-ghost min-h-12 justify-center">
              <Icon name="link" size={17} /> {t("calc.ctaLink")}
            </Link>
          )}
          <Link href="/categories" className={"btn btn-primary min-h-12 justify-center " + (requests ? "" : "sm:col-span-2")}>
            {t("calc.ctaShop")} <Icon name="arrow" size={16} />
          </Link>
        </div>
      </section>
    </div>
  );
}
