"use client";

import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled } from "@/lib/apiClient";
import { emiPlan, money, type EmiConfig, type MoneyQuote } from "@/lib/money";
import type { CartItem } from "@/lib/types";
import "./money.css";
import { useT } from "@/i18n/client";

export type EmiChoice = { tenure: number; bank: string };

/**
 * Payment step: "Card EMI" option with a tenure picker, bank and a monthly preview. Picking it means
 * payment "card_emi" (SSLCommerz with EMI); charges are shown before the order is placed.
 */
export function EmiOption({ amount, value, onChange }: { amount: number; value: EmiChoice | null; onChange: (v: EmiChoice | null) => void }) {
  const on = useFeature("pay.emi");
  const tx = useT();
  const { t: tr, bdt, digits } = tx;
  const [cfg, setCfg] = useState<EmiConfig | null>(null);
  useEffect(() => {
    if (!on) return;
    money
      .emiConfig()
      .then(setCfg)
      .catch(() => {});
  }, [on]);
  if (!on || !cfg || !cfg.enabled || !cfg.tenures.length) return null;

  const eligible = amount >= cfg.minOrderBdt;
  const active = !!value && eligible;
  const tenure = value?.tenure ?? cfg.tenures[Math.min(1, cfg.tenures.length - 1)];
  const banks = cfg.banks.filter((b) => b.rates[String(tenure)] !== undefined);
  const bank = value?.bank && banks.some((b) => b.code === value.bank) ? value.bank : (banks[0]?.code ?? "");
  const plan = emiPlan(cfg, amount, tenure, bank || null, tx);

  return (
    <div className={"co-option flex-col items-stretch " + (active ? "is-active" : "")}>
      <label className="flex cursor-pointer items-center gap-3">
        <input
          type="radio"
          name="payment"
          className="sr-only"
          checked={active}
          disabled={!eligible}
          onChange={() => onChange({ tenure, bank })}
        />
        <span className={"chip-icon shrink-0 " + (active ? "is-active" : "")}>
          <Icon name="card" size={18} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-sm font-bold">{tr("emi.label")}</span>
          <span className="block text-xs text-ink-2">
            {eligible
              ? tr("emi.payIn", { tenures: digits(cfg.tenures.join(", ")) })
              : tr("emi.availableOn", { amount: bdt(cfg.minOrderBdt) })}
          </span>
        </span>
        <span className="co-radio" aria-hidden />
      </label>

      {active && (
        <div className="money-in mt-3 space-y-3 border-t border-dashed border-line pt-3">
          <div role="radiogroup" aria-label={tr("emi.tenure")} className="flex flex-wrap gap-2">
            {cfg.tenures.map((t) => (
              <button key={t} type="button" role="radio" aria-checked={t === tenure} className="money-chip text-sm" onClick={() => onChange({ tenure: t, bank })}>
                {tr("emi.months", { count: t })}
              </button>
            ))}
          </div>
          {banks.length > 0 && (
            <label className="block text-xs font-semibold text-ink-2">
              {tr("emi.bank")}
              <select
                value={bank}
                onChange={(e) => onChange({ tenure, bank: e.target.value })}
                className="mt-1 block min-h-11 w-full rounded-2xl border border-line bg-surface px-3 text-sm text-ink outline-none focus:border-accent"
              >
                {banks.map((b) => (
                  <option key={b.code} value={b.code}>
                    {tr("emi.bankCharge", { bank: b.name, pct: b.rates[String(tenure)] })}
                  </option>
                ))}
              </select>
            </label>
          )}
          {plan.eligible ? (
            <dl className="grid grid-cols-3 gap-2 rounded-2xl bg-surface-2 p-3 text-center text-xs">
              <div>
                <dt className="text-ink-3">{tr("emi.monthly")}</dt>
                <dd key={plan.monthlyBdt} className="money-count text-base font-extrabold tabular-nums text-ink">
                  {bdt(plan.monthlyBdt)}
                </dd>
              </div>
              <div>
                <dt className="text-ink-3">{tr("emi.bankChargeLabel")}</dt>
                <dd className="text-base font-extrabold tabular-nums text-ink">{plan.chargeBdt ? bdt(plan.chargeBdt) : digits("0%")}</dd>
              </div>
              <div>
                <dt className="text-ink-3">{tr("sum.total")}</dt>
                <dd className="text-base font-extrabold tabular-nums text-ink">{bdt(plan.totalBdt)}</dd>
              </div>
            </dl>
          ) : (
            <p className="text-xs font-semibold text-warn">{plan.reason}</p>
          )}
          <p className="text-[11px] text-ink-3">
            {tr("emi.note", { pct: plan.ratePct })}
          </p>
        </div>
      )}
    </div>
  );
}

/**
 * Review step: wallet toggle, Skydrop Plus savings and the EMI charge from the server quote, with "Pay now".
 * Calls onQuote with the server quote (or null) so the place-order button can show the amount due now.
 */
export function CheckoutMoney({
  items,
  coupon,
  redeemPoints,
  payment,
  emi,
  useWallet,
  setUseWallet,
  onQuote,
  lock,
}: {
  items: CartItem[];
  coupon?: string;
  redeemPoints?: number;
  payment: string | null;
  emi: EmiChoice | null;
  useWallet: boolean;
  setUseWallet: (v: boolean) => void;
  onQuote: (q: MoneyQuote | null) => void;
  lock?: boolean;
}) {
  const walletOn = useFeature("wallet");
  const { t, bdt } = useT();
  const [q, setQ] = useState<MoneyQuote | null>(null);
  const key = items.map((i) => `${i.productId}:${i.variant ?? ""}:${i.qty}:${i.unitBdt}`).join("|") + `|${coupon}|${redeemPoints}|${payment}|${emi?.tenure}|${emi?.bank}|${useWallet}`;

  useEffect(() => {
    if (!apiEnabled || !items.length) return;
    let live = true;
    const t = setTimeout(() => {
      money
        .quote(items, {
          coupon,
          redeemPoints,
          useWallet: walletOn && useWallet,
          payment: emi ? "card_emi" : (payment ?? undefined),
          emiTenure: emi?.tenure,
          emiBank: emi?.bank,
        })
        .then((r) => {
          if (!live) return;
          setQ(r);
          onQuote(r);
        })
        .catch(() => {
          if (!live) return;
          setQ(null);
          onQuote(null);
        });
    }, 250);
    return () => {
      live = false;
      clearTimeout(t);
    };
    // key captures every input
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  if (!apiEnabled || !q) return null;
  const balance = q.wallet?.balanceBdt ?? 0;
  const lines = [
    ...(q.membership?.lines ?? []).map((l) => ({ ...l, sign: "−", tone: "text-good", icon: "star" })),
    ...(q.walletApplied > 0 ? [{ key: "wallet", label: t("wallet.name"), amountBdt: q.walletApplied, sign: "−", tone: "text-good", icon: "wallet" }] : []),
    ...(q.emiCharge > 0 && q.emi ? [{ key: "emi", label: t("wallet.emiCharge", { bank: q.emi.bankName ?? t("wallet.bank"), pct: q.emi.ratePct }), amountBdt: q.emiCharge, sign: "+", tone: "", icon: "card" }] : []),
  ];

  return (
    <div className="space-y-3">
      {walletOn && balance > 0 && (
        <label className={"co-option items-center " + (useWallet ? "is-active" : "")}>
          <input type="checkbox" className="sr-only" checked={useWallet} disabled={lock} onChange={(e) => setUseWallet(e.target.checked)} />
          <span className="chip-icon shrink-0">
            <Icon name="wallet" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-bold">{t("wallet.use")}</span>
            <span className="block text-xs text-ink-2">
              {useWallet && q.walletApplied > 0
                ? t("wallet.applied", { amount: bdt(q.walletApplied), left: bdt(balance - q.walletApplied) })
: t("wallet.balance", { amount: bdt(balance) })}
            </span>
          </span>
          <span className={"rounded-full px-2.5 py-1 text-[11px] font-extrabold " + (useWallet ? "bg-accent text-white" : "bg-surface-2 text-ink-2")}>
            {useWallet ? t("f.on") : t("f.off")}
          </span>
        </label>
      )}

      {(lines.length > 0 || q.payNow !== q.total) && (
        <dl className="co-card money-in space-y-2 p-4 text-sm">
          {q.membership && (
            <p className="mb-1 flex items-center gap-2 text-xs font-bold text-good">
              <span className="plus-badge">PLUS</span> {t("plus.saved", { amount: bdt(q.membership.savedBdt) })}
              {q.membership.pointsMultiplier > 1 ? ` · ${q.membership.pointsMultiplier}× points` : ""}
            </p>
          )}
          {lines.map((l) => (
            <div key={l.key} className={"flex justify-between gap-3 " + l.tone}>
              <dt className="inline-flex items-center gap-1.5">
                <Icon name={l.icon} size={14} /> {l.label}
              </dt>
              <dd className="font-semibold tabular-nums">
                {l.sign}
                {bdt(l.amountBdt)}
              </dd>
            </div>
          ))}
          <div className="flex items-baseline justify-between gap-3 border-t border-dashed border-line pt-2">
            <dt className="font-bold">{t("money.orderTotal")}</dt>
            <dd className="font-extrabold tabular-nums">{bdt(q.total)}</dd>
          </div>
          {q.payNow !== q.total && (
            <div className="flex items-baseline justify-between gap-3">
              <dt className="font-bold">{t("money.payNow")}</dt>
              <dd key={q.payNow} className="co-bump text-lg font-extrabold tabular-nums">
                {bdt(q.payNow)}
              </dd>
            </div>
          )}
        </dl>
      )}
    </div>
  );
}
