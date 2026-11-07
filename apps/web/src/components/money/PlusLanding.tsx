"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { ApiError, apiEnabled, goToPayment } from "@/lib/apiClient";
import { DEMO_PLANS, PERIOD_LABEL, money, type PlusMembership, type PlusPeriod, type PlusPlan, type PlusSavings } from "@/lib/money";
import { useStore } from "@/state/store";
import "./money.css";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const PERIODS: { id: PlusPeriod; label: MsgKey }[] = [
  { id: "monthly", label: "pl.monthly" },
  { id: "quarterly", label: "pl.quarterly" },
  { id: "yearly", label: "pl.yearly" },
];
const PAY = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
  { id: "card", label: "pay.card" },
] as const;

export function PlusLanding({ renew }: { renew?: string }) {
  const { user } = useStore();
  const on = useFeature("membership");
  const toast = useToast();
  const { t, bdt, date } = useT();
  const router = useRouter();
  const [plans, setPlans] = useState<PlusPlan[]>(apiEnabled ? [] : DEMO_PLANS);
  const [loaded, setLoaded] = useState(!apiEnabled);
  const [period, setPeriod] = useState<PlusPeriod>("quarterly");
  const [me, setMe] = useState<{ membership: PlusMembership | null; savings: PlusSavings } | null>(null);
  const [picked, setPicked] = useState<string | null>(renew ?? null);
  const [pay, setPay] = useState<(typeof PAY)[number]["id"]>("bkash");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!on) return;
    money
      .plans()
      .then((r) => {
        setPlans(r.items);
        const periods = new Set(r.items.map((p) => p.period));
        const renewing = renew ? r.items.find((p) => p.slug === renew) : undefined;
        if (renewing) setPeriod(renewing.period);
        else if (!periods.has("quarterly") && r.items[0]) setPeriod(r.items[0].period);
      })
      .catch(() => {})
      .finally(() => setLoaded(true));
  }, [on, renew]);

  useEffect(() => {
    if (!on || !apiEnabled || !user) return;
    money
      .membership()
      .then(setMe)
      .catch(() => {});
  }, [on, user]);

  const periods = useMemo(() => PERIODS.filter((p) => plans.some((x) => x.period === p.id)), [plans]);
  const shown = plans.filter((p) => p.period === period);
  const idx = Math.max(0, periods.findIndex((p) => p.id === period));
  const current = me?.membership && me.membership.status !== "expired" ? me.membership : null;

  if (!on)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 text-center md:pb-12">
        <h1 className="text-2xl font-extrabold">Skydrop Plus</h1>
        <p className="mt-2 text-sm text-ink-2">{t("pl.off")}</p>
      </div>
    );

  const buy = async (plan: PlusPlan) => {
    if (busy) return;
    if (!apiEnabled) {
      toast({ kind: "info", title: t("gc.previewStore"), body: t("pl.previewB") });
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const r = await money.buyMembership(plan.slug, pay, `${window.location.origin}/plus/success`);
      if (r.payment.redirectUrl) goToPayment(r.payment.redirectUrl);
      else router.push(`/plus/success?order=${r.order.id}&status=${r.payment.status === "paid" ? "paid" : "failed"}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("gc.payFail"));
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-6xl px-4 pb-24 pt-6 sm:px-6 md:pb-12">
      <section className="plus-hero rise p-6 sm:p-10" aria-labelledby="plus-h">
        <div className="relative z-10 max-w-xl">
          <span className="plus-badge">
            <Icon name="star" size={12} /> SKYDROP PLUS
          </span>
          <h1 id="plus-h" className="mt-3 text-3xl font-extrabold tracking-tight sm:text-5xl">
            {t("pl.title")} <span className="plus-gold">{t("pl.every")}</span>
          </h1>
          <p className="mt-3 text-sm text-white/80 sm:text-base">
            {t("pl.sub")}
          </p>
          {current && (
            <p className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold">
              <Icon name="check" size={13} /> {t("pl.youreOn", { plan: current.plan?.name })}
              {current.renewsAt && t(current.status === "cancelled" ? "pl.ends" : "pl.paidThrough", { date: date(current.renewsAt, { day: "numeric", month: "short", year: "numeric" }) })}
            </p>
          )}
        </div>
      </section>

      {me && current && <SavingsTracker savings={me.savings} />}

      {periods.length > 1 && (
        <div role="radiogroup" aria-label={t("pl.billing")} className="plus-toggle mx-auto mt-8 max-w-sm" style={{ ["--n" as string]: periods.length }}>
          <span aria-hidden className="plus-toggle-pill" style={{ ["--idx" as string]: idx }} />
          {periods.map((p) => (
            <button key={p.id} type="button" role="radio" aria-checked={p.id === period} onClick={() => setPeriod(p.id)}>
              {t(p.label)}
            </button>
          ))}
        </div>
      )}

      {!loaded ? (
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="skeleton h-80 rounded-[12px]" />
          ))}
        </div>
      ) : plans.length === 0 ? (
        <p className="mt-8 text-center text-sm text-ink-2">{t("pl.soon")}</p>
      ) : (
        <ul key={period} className="mt-6 grid gap-4 md:grid-cols-[repeat(auto-fit,minmax(260px,1fr))]">
          {shown.map((p, i) => {
            const isCurrent = current?.plan?.slug === p.slug;
            const open = picked === p.slug;
            return (
              <li key={p.id} className="card plus-plan rise flex flex-col p-5" data-highlight={p.highlight} style={{ ["--i" as string]: i }}>
                <div className="flex items-center justify-between gap-2">
                  <h2 className="text-lg font-extrabold">{p.name}</h2>
                  {p.highlight && <span className="plus-badge">{t("pl.popular")}</span>}
                </div>
                {p.tagline && <p className="text-xs text-ink-2">{p.tagline}</p>}
                <p className="mt-3">
                  <span className="text-3xl font-extrabold tabular-nums tracking-tight">{bdt(p.priceBdt)}</span>
                  <span className="text-sm text-ink-2"> / {t(PERIOD_LABEL[p.period])}</span>
                </p>
                {p.months > 1 && <p className="text-xs font-semibold text-good">{t("pl.aMonth", { amount: bdt(p.monthlyBdt) })}</p>}
                <ul className="mt-4 flex-1 space-y-2 text-sm">
                  {p.benefitList.map((b) => (
                    <li key={b} className="flex items-start gap-2">
                      <Icon name="check" size={16} stroke={2.6} className="mt-0.5 shrink-0 text-good" /> {b}
                    </li>
                  ))}
                </ul>
                {!user ? (
                  <Link href={`/login?next=${encodeURIComponent("/plus")}`} className="btn btn-primary mt-5 min-h-11 w-full">
                    {t("pl.signIn")}
                  </Link>
                ) : !open ? (
                  <button type="button" onClick={() => setPicked(p.slug)} className={"btn mt-5 min-h-11 w-full " + (p.highlight ? "btn-primary" : "btn-ghost")}>
                    {isCurrent ? t("pl.renew") : current ? t("pl.switch") : t("pl.join")}
                  </button>
                ) : (
                  <div className="money-in mt-5 space-y-3">
                    <div role="radiogroup" aria-label={t("pl.payWith")} className="grid grid-cols-3 gap-2">
                      {PAY.map((m) => (
                        <button key={m.id} type="button" role="radio" aria-checked={pay === m.id} className="money-chip px-2 text-sm" onClick={() => setPay(m.id)}>
                          {m.id === "card" ? t("pay.card") : m.label}
                        </button>
                      ))}
                    </div>
                    <button type="button" disabled={busy} onClick={() => buy(p)} className="btn btn-primary min-h-11 w-full">
                      {busy ? <Icon name="refresh" size={16} className="animate-spin" /> : <Icon name="shield" size={16} />} {t("gc.pay", { amount: bdt(p.priceBdt) })}
                    </button>
                    {current && <p className="text-[11px] text-ink-3">{t("pl.added")}</p>}
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}

      {error && (
        <p role="alert" className="shake mx-auto mt-4 max-w-md rounded-2xl bg-bad/10 p-3 text-center text-sm font-semibold text-bad">
          {error}
        </p>
      )}

      <p className="mx-auto mt-8 max-w-2xl text-center text-xs text-ink-3">
        {t("pl.noAuto")}
      </p>
    </div>
  );
}

export function SavingsTracker({ savings, compact }: { savings: PlusSavings; compact?: boolean }) {
  const { t, bdt } = useT();
  const ratio = savings.paidBdt > 0 ? Math.min(1, savings.totalBdt / savings.paidBdt) : savings.totalBdt > 0 ? 1 : 0;
  return (
    <section className={"card rise " + (compact ? "p-4" : "mt-4 p-5")} aria-label={t("pl.savingsAria")}>
      <div className="flex items-baseline justify-between gap-3">
        <p className="text-sm font-bold">{t("pl.savedYou")}</p>
        <p key={savings.totalBdt} className="money-count text-2xl font-extrabold tabular-nums text-good">
          {bdt(savings.totalBdt)}
        </p>
      </div>
      <div className="plus-meter mt-3" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(ratio * 100)} aria-label={t("pl.meter")}>
        <span style={{ ["--p" as string]: ratio }} />
      </div>
      <p className="mt-2 text-xs text-ink-2">
        {t("pl.onOrders", { amount: bdt(savings.ordersBdt), count: savings.orders })}
{savings.creditsBdt > 0 ? t("pl.credit", { amount: bdt(savings.creditsBdt) }) : ""}
{savings.paidBdt > 0 ? (savings.totalBdt >= savings.paidBdt ? t("pl.paidItself") : t("pl.toPay", { amount: bdt(savings.paidBdt - savings.totalBdt) })) : ""}
      </p>
    </section>
  );
}
