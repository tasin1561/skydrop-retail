"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { ApiError, apiEnabled } from "@/lib/apiClient";
import { DEMO_PLANS, PERIOD_LABEL, money, type PlusMembership, type PlusPeriod, type PlusPlan, type PlusSavings } from "@/lib/money";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";
import "./money.css";

const PERIODS: { id: PlusPeriod; label: string }[] = [
  { id: "monthly", label: "Monthly" },
  { id: "quarterly", label: "Quarterly" },
  { id: "yearly", label: "Yearly" },
];
const PAY = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
  { id: "card", label: "Card" },
] as const;

export function PlusLanding({ renew }: { renew?: string }) {
  const { user } = useStore();
  const on = useFeature("membership");
  const toast = useToast();
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
        <p className="mt-2 text-sm text-ink-2">Skydrop Plus is not available right now.</p>
      </div>
    );

  const buy = async (plan: PlusPlan) => {
    if (busy) return;
    if (!apiEnabled) {
      toast({ kind: "info", title: "Preview store", body: "Plus can be bought once payments are connected." });
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const r = await money.buyMembership(plan.slug, pay, `${window.location.origin}/plus/success`);
      if (r.payment.redirectUrl) window.location.assign(r.payment.redirectUrl);
      else router.push(`/plus/success?order=${r.order.id}&status=${r.payment.status === "paid" ? "paid" : "failed"}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "We couldn't start the payment. Nothing was charged.");
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
            Free shipping from India. <span className="plus-gold">Every order.</span>
          </h1>
          <p className="mt-3 text-sm text-white/80 sm:text-base">
            Plus members skip Skydrop shipping fees, get extra off, earn more points and shop flash sales first.
          </p>
          {current && (
            <p className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold">
              <Icon name="check" size={13} /> You&apos;re on {current.plan?.name}
              {current.renewsAt && ` · ${current.status === "cancelled" ? "ends" : "paid through"} ${new Date(current.renewsAt).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`}
            </p>
          )}
        </div>
      </section>

      {me && current && <SavingsTracker savings={me.savings} />}

      {periods.length > 1 && (
        <div role="radiogroup" aria-label="Billing period" className="plus-toggle mx-auto mt-8 max-w-sm" style={{ ["--n" as string]: periods.length }}>
          <span aria-hidden className="plus-toggle-pill" style={{ ["--idx" as string]: idx }} />
          {periods.map((p) => (
            <button key={p.id} type="button" role="radio" aria-checked={p.id === period} onClick={() => setPeriod(p.id)}>
              {p.label}
            </button>
          ))}
        </div>
      )}

      {!loaded ? (
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="skeleton h-80 rounded-[20px]" />
          ))}
        </div>
      ) : plans.length === 0 ? (
        <p className="mt-8 text-center text-sm text-ink-2">Plans are coming soon.</p>
      ) : (
        <ul key={period} className="mt-6 grid gap-4 md:grid-cols-[repeat(auto-fit,minmax(260px,1fr))]">
          {shown.map((p, i) => {
            const isCurrent = current?.plan?.slug === p.slug;
            const open = picked === p.slug;
            return (
              <li key={p.id} className="card plus-plan rise flex flex-col p-5" data-highlight={p.highlight} style={{ ["--i" as string]: i }}>
                <div className="flex items-center justify-between gap-2">
                  <h2 className="text-lg font-extrabold">{p.name}</h2>
                  {p.highlight && <span className="plus-badge">Most popular</span>}
                </div>
                {p.tagline && <p className="text-xs text-ink-2">{p.tagline}</p>}
                <p className="mt-3">
                  <span className="text-3xl font-extrabold tabular-nums tracking-tight">{formatBdt(p.priceBdt)}</span>
                  <span className="text-sm text-ink-2"> / {PERIOD_LABEL[p.period]}</span>
                </p>
                {p.months > 1 && <p className="text-xs font-semibold text-good">{formatBdt(p.monthlyBdt)} a month</p>}
                <ul className="mt-4 flex-1 space-y-2 text-sm">
                  {p.benefitList.map((b) => (
                    <li key={b} className="flex items-start gap-2">
                      <Icon name="check" size={16} stroke={2.6} className="mt-0.5 shrink-0 text-good" /> {b}
                    </li>
                  ))}
                </ul>
                {!user ? (
                  <Link href={`/login?next=${encodeURIComponent("/plus")}`} className="btn btn-primary mt-5 min-h-11 w-full">
                    Sign in to join
                  </Link>
                ) : !open ? (
                  <button type="button" onClick={() => setPicked(p.slug)} className={"btn mt-5 min-h-11 w-full " + (p.highlight ? "btn-primary" : "btn-ghost")}>
                    {isCurrent ? "Renew now" : current ? "Switch to this plan" : "Join Plus"}
                  </button>
                ) : (
                  <div className="money-in mt-5 space-y-3">
                    <div role="radiogroup" aria-label="Pay with" className="grid grid-cols-3 gap-2">
                      {PAY.map((m) => (
                        <button key={m.id} type="button" role="radio" aria-checked={pay === m.id} className="money-chip px-2 text-sm" onClick={() => setPay(m.id)}>
                          {m.label}
                        </button>
                      ))}
                    </div>
                    <button type="button" disabled={busy} onClick={() => buy(p)} className="btn btn-primary min-h-11 w-full">
                      {busy ? <Icon name="refresh" size={16} className="animate-spin" /> : <Icon name="shield" size={16} />} Pay {formatBdt(p.priceBdt)}
                    </button>
                    {current && <p className="text-[11px] text-ink-3">Time is added after your current paid-through date.</p>}
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
        Plus does not renew automatically. We&apos;ll text you a few days before it ends with a link to renew with bKash, Nagad or card. Benefits apply
        to orders placed while your plan is active.
      </p>
    </div>
  );
}

export function SavingsTracker({ savings, compact }: { savings: PlusSavings; compact?: boolean }) {
  const ratio = savings.paidBdt > 0 ? Math.min(1, savings.totalBdt / savings.paidBdt) : savings.totalBdt > 0 ? 1 : 0;
  return (
    <section className={"card rise " + (compact ? "p-4" : "mt-4 p-5")} aria-label="Plus savings">
      <div className="flex items-baseline justify-between gap-3">
        <p className="text-sm font-bold">Plus saved you</p>
        <p key={savings.totalBdt} className="money-count text-2xl font-extrabold tabular-nums text-good">
          {formatBdt(savings.totalBdt)}
        </p>
      </div>
      <div className="plus-meter mt-3" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(ratio * 100)} aria-label="Savings compared with what you paid">
        <span style={{ ["--p" as string]: ratio }} />
      </div>
      <p className="mt-2 text-xs text-ink-2">
        {formatBdt(savings.ordersBdt)} on {savings.orders} order{savings.orders === 1 ? "" : "s"}
        {savings.creditsBdt > 0 ? ` + ${formatBdt(savings.creditsBdt)} wallet credit` : ""}
        {savings.paidBdt > 0 ? (savings.totalBdt >= savings.paidBdt ? " · Plus has paid for itself" : ` · ${formatBdt(savings.paidBdt - savings.totalBdt)} to pay for itself`) : ""}
      </p>
    </section>
  );
}
