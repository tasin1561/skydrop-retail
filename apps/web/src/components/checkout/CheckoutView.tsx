"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import { STORES } from "@/lib/markets";
import type { CartItem, Order } from "@/lib/types";
import { useStore } from "@/state/store";
import { api, apiEnabled, ApiError, type PriceChange } from "@/lib/apiClient";
import { getRef } from "@/lib/growth";
import { trackCheckout } from "@/lib/track";
import { demoLedger, maxRedeem } from "@/lib/points";
import { AddressStep, addressText } from "./AddressStep";
import { computeTotals, useCoupon } from "./cart-math";
import { EmptyCart } from "./EmptyCart";
import { DeliveryStep, PAYMENTS, PaymentStep, paymentLabel, type PaymentId } from "./Options";
import { QuickSignIn } from "./QuickSignIn";
import { STEPS, Stepper } from "./Stepper";
import { DeliveryPromise, SummaryLines, TrustRow } from "./Summary";
import { VanButton, type VanState } from "./VanButton";
import { CheckoutRestrictions } from "@/components/cms/CheckoutRestrictions";
import { CHECKS, VerifyPanel, type CheckState, type VerifyResult } from "./VerifyPanel";
import { UnboxingToggle, unboxingFeeFor } from "@/components/fulfilment/UnboxingToggle";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useBundleTotals } from "@/components/shopping/useBundleTotals";
import { CheckoutMoney, EmiOption, type EmiChoice } from "@/components/money/CheckoutMoney";
import type { MoneyQuote } from "@/lib/money";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";

const ALNUM = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
function rand(n: number, chars: string) {
  const a = new Uint32Array(n);
  crypto.getRandomValues(a);
  return Array.from(a, (x) => chars[x % chars.length]).join("");
}
const wait = (ms: number) => new Promise((r) => setTimeout(r, ms));
const itemKey = (i: CartItem) => i.productId + "::" + (i.variant ?? "");

// Timing helpers for the order animation (called from event handlers only).
const nowMs = () => Date.now();
const msSince = (t: number) => Date.now() - t;

type Phase = "idle" | "verifying" | "confirm" | "blocked" | "error" | "placing" | "done";

export function CheckoutView() {
  const store = useStore();
  const tx = useT();
  const { t: tr, bdt, lang } = tx;
  const { ready, user, addresses, cart, placeOrder } = store;
  const router = useRouter();
  const [coupon, setCoupon] = useCoupon();

  const [step, setStep] = useState(0);
  const [reached, setReached] = useState(0);
  const [addrPick, setAddrPick] = useState<string | null>(null);
  const [payment, setPayment] = useState<PaymentId | null>(null);
  const [terms, setTerms] = useState(false);
  const [usePoints, setUsePoints] = useState(false);
  const [unboxing, setUnboxing] = useState(false); // fulfilment: paid unboxing at the Kolkata hub
  const unboxingOn = useFeature("unboxing");
  // money module: store credit, card EMI and the server quote (Plus savings, wallet, EMI charge, pay now)
  const [useWallet, setUseWallet] = useState(false);
  const [emi, setEmi] = useState<EmiChoice | null>(null);
  const [moneyQ, setMoneyQ] = useState<MoneyQuote | null>(null);
  const [apiPoints, setApiPoints] = useState<number | null>(null);
  const [nudge, setNudge] = useState<{ n: number; msg: string } | null>(null);

  const [phase, setPhase] = useState<Phase>("idle");
  const [checks, setChecks] = useState<CheckState[]>(["wait", "wait", "wait"]);
  const [fresh, setFresh] = useState<Record<string, number>>({});
  const [failed, setFailed] = useState<CartItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [placed, setPlaced] = useState<Order | null>(null);
  const alive = useRef(true);
  const panelRef = useRef<HTMLDivElement>(null);
  const headRef = useRef<HTMLHeadingElement>(null);

  useEffect(() => {
    if (!apiEnabled || !user) return;
    api
      .points()
      .then((p) => setApiPoints(p.balance))
      .catch(() => setApiPoints(0));
  }, [user]);

  useEffect(() => {
    alive.current = true;
    return () => {
      alive.current = false;
    };
  }, []);

  useEffect(() => {
    if (ready) trackCheckout("InitiateCheckout", cart);
  }, [ready, cart]);

  // After placeOrder clears the cart, keep rendering the order that was just placed.
  const items: CartItem[] = placed
    ? placed.items
    : cart.map((i) => (fresh[itemKey(i)] ? { ...i, unitBdt: fresh[itemKey(i)] } : i));
  const pointsBalance = apiEnabled ? (apiPoints ?? 0) : demoLedger(store.orders, user ? "" : undefined).balance;
  const redeemable = maxRedeem(pointsBalance, computeTotals(items, coupon).subtotal);
  const t0 = useBundleTotals(computeTotals(items, coupon, usePoints && !placed ? redeemable.points : (placed?.pointsRedeemed ?? 0)), items); // Shopping: bundle saving
  const unboxingFee = unboxingFeeFor(unboxingOn && unboxing, t0.groups.length);
  const t = unboxingFee ? { ...t0, unboxingFee, total: t0.total + unboxingFee } : t0;
  const address = addresses.find((a) => a.id === addrPick) ?? addresses.find((a) => a.isDefault) ?? addresses[0] ?? null;

  if (!ready) return <CheckoutSkeleton />;
  if (!cart.length && !placed) return <EmptyCart />;

  const go = (i: number) => {
    setStep(i);
    setReached((r) => Math.max(r, i));
    setNudge(null);
    requestAnimationFrame(() => {
      headRef.current?.focus({ preventScroll: true });
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  };

  const blockedReason = !user
    ? tr("co.blockSignIn")
    : step === 0 && !address
      ? tr("co.blockAddress")
      : step === 2 && !payment
        ? tr("co.blockPayment")
        : step === 3 && !terms
          ? tr("co.blockTerms")
          : null;

  const next = () => {
    if (blockedReason) {
      setNudge((n) => ({ n: (n?.n ?? 0) + 1, msg: blockedReason }));
      return;
    }
    // funnel events (once per cart contents, see trackCheckout)
    if (step === 0) trackCheckout("AddShippingInfo", items, { value: t.total, shipping: t.shipping, coupon: coupon?.code });
    if (step === 2) trackCheckout("AddPaymentInfo", items, { value: t.total, method: payment ?? undefined, coupon: coupon?.code });
    if (step < 3) go(step + 1);
    else placeFlow();
  };

  const finish = async (orderItems: CartItem[], startedAt: number) => {
    if (!address || !payment) return;
    setPhase("placing");
    const tt = computeTotals(orderItems, coupon, usePoints ? redeemable.points : 0);
    if (apiEnabled) {
      // Live API: the server re-checks prices, computes totals and starts the payment.
      try {
        const [res] = await Promise.all([
          api.createOrder({
            items: orderItems,
            address,
            payment: emi ? "card_emi" : payment,
            coupon: coupon?.code,
            redeemPoints: tt.pointsRedeemed || undefined,
            unboxing: unboxingOn && unboxing ? true : undefined,
            returnUrl: `${window.location.origin}/checkout/success`,
            ref: getRef(),
            extra: { useWallet: useWallet || undefined, emiTenure: emi?.tenure, emiBank: emi?.bank },
          }),
          wait(Math.max(0, 700 - (msSince(startedAt)))),
        ]);
        if (!alive.current) return;
        setPlaced(res.order);
        placeOrder(res.order);
        setPhase("done");
        await wait(900);
        setCoupon("");
        if (res.payment.redirectUrl) window.location.assign(res.payment.redirectUrl);
        else if (alive.current) router.push(`/checkout/success?order=${res.order.id}`);
      } catch (err) {
        if (!alive.current) return;
        const body = err instanceof ApiError ? (err.data as { prices?: PriceChange[]; items?: { productId: string }[] } | undefined) : undefined;
        if (err instanceof ApiError && err.status === 409 && body?.prices?.length) {
          // Store prices moved since the quote: update the cart to the fresh prices and let the shopper re-confirm.
          store.repriceCart(body.prices);
          const up = body.prices.filter((x) => x.freshBdt > x.oldBdt).length;
          setError(
            tr("co.pricesChanged", { count: body.prices.length }) + (up ? tr("co.someUp") : "") + tr("co.cartUpdated"),
          );
          setPhase("error");
          return;
        }
        setError(
          err instanceof ApiError && err.status === 409
            ? tr("co.pricesMoved")
            : err instanceof ApiError && err.status === 422 && body?.items?.length
              ? tr("co.unavailable", { count: body.items.length })
              : err instanceof ApiError
              ? err.message
              : tr("co.failed"),
        );
        setPhase("error");
      }
      return;
    }
    const order: Order = {
      id: "SR-" + rand(6, ALNUM),
      createdAt: new Date().toISOString(),
      items: orderItems,
      address,
      payment: paymentLabel(payment, tt.total, tx),
      subtotal: tt.subtotal,
      shipping: tt.shipping,
      discount: tt.discount,
      total: tt.total + unboxingFee, // fulfilment: unboxing fee (demo)
      status: "placed",
      trackingCode: "SD" + rand(9, "0123456789"),
      pointsRedeemed: tt.pointsRedeemed,
      pointsDiscount: tt.pointsDiscount,
      pointsEarned: tt.pointsToEarn,
    };
    const left = 700 - (msSince(startedAt));
    if (left > 0) await wait(left);
    if (!alive.current) return;
    setPlaced(order);
    placeOrder(order);
    setPhase("done");
    await wait(900); // let the van drive off
    // Coupon is used up; clear it only after leaving so the totals on screen don't jump mid-animation.
    if (alive.current) router.push(`/checkout/success?order=${order.id}`);
    setCoupon("");
  };

  const placeFlow = async () => {
    const startedAt = nowMs();
    // Customer already confirmed the changed prices: place at those prices.
    if (phase === "confirm") {
      finish(items, startedAt);
      return;
    }
    setPhase("verifying");
    setError(null);
    setFailed([]);
    setChecks(["run", "wait", "wait"]);
    requestAnimationFrame(() => panelRef.current?.scrollIntoView({ behavior: "smooth", block: "nearest" }));
    const t1 = setTimeout(() => alive.current && setChecks((c) => (c[1] === "wait" ? ["run", "run", "wait"] : c)), 380);
    const t2 = setTimeout(() => alive.current && setChecks((c) => (c[2] === "wait" ? ["run", "run", "run"] : c)), 760);

    let results: VerifyResult[];
    try {
      const [res] = await Promise.all([
        fetch("/api/verify", {
          method: "POST",
          headers: { "content-type": "application/json" },
          body: JSON.stringify({ items: cart.map((i) => ({ productId: i.productId, unitBdt: i.unitBdt })) }),
        }),
        wait(700),
      ]);
      if (!res.ok) throw new Error("bad status");
      const data = (await res.json()) as { results?: VerifyResult[] };
      results = data.results ?? [];
    } catch {
      clearTimeout(t1);
      clearTimeout(t2);
      if (!alive.current) return;
      setChecks((c) => c.map((s) => (s === "ok" ? s : "fail")) as CheckState[]);
      setError(tr("co.unreachable"));
      setPhase("error");
      return;
    }
    clearTimeout(t1);
    clearTimeout(t2);
    if (!alive.current) return;

    const byId = new Map(results.map((r) => [r.productId, r]));
    const bad = cart.filter((i) => !byId.get(i.productId)?.ok);
    const undeliverable = cart.filter((i) => byId.get(i.productId)?.ok && byId.get(i.productId)?.deliverable === false);
    const changed: Record<string, number> = {};
    for (const i of cart) {
      const r = byId.get(i.productId);
      if (r?.ok && r.changed && typeof r.freshBdt === "number" && r.freshBdt !== i.unitBdt) changed[itemKey(i)] = r.freshBdt;
    }

    // tick the checks off one by one
    const outcome: CheckState[] = [
      bad.length ? "fail" : Object.keys(changed).length ? "warn" : "ok",
      bad.length ? "fail" : "ok",
      undeliverable.length ? "fail" : bad.length ? "fail" : "ok",
    ];
    for (let i = 0; i < CHECKS.length; i++) {
      setChecks((c) => c.map((s, j) => (j === i ? outcome[i] : s)) as CheckState[]);
      await wait(240);
      if (!alive.current) return;
    }

    if (bad.length || undeliverable.length) {
      setFailed([...bad, ...undeliverable]);
      setPhase("blocked");
      return;
    }
    if (Object.keys(changed).length) {
      setFresh(changed);
      setPhase("confirm");
      return;
    }
    finish(cart, startedAt);
  };

  const van: VanState = phase === "verifying" || phase === "placing" ? "busy" : phase === "done" ? "done" : "idle";
  const changedList = Object.entries(fresh)
    .map(([k, f]) => ({ item: cart.find((i) => itemKey(i) === k), fresh: f }))
    .filter((x): x is { item: CartItem; fresh: number } => !!x.item);

  const actionLabel =
    step < 3 ? (
      <>
        {tr("co.continueTo", { step: tr(STEPS[step + 1].label).toLowerCase() })} <Icon name="arrow" size={18} className="arrow" />
      </>
    ) : phase === "confirm" ? (
      <>{tr("co.confirmPrices", { amount: bdt(t.total) })}</>
    ) : phase === "error" ? (
      <>
        <Icon name="refresh" size={17} /> {tr("common.tryAgain")}
      </>
    ) : (
      <>
        <Icon name="shield" size={17} /> {tr("co.placeOrder", { amount: bdt(step === 3 && moneyQ ? moneyQ.payNow : t.total) })}
      </>
    );

  const action = (cls: string) =>
    step < 3 ? (
      <button type="button" onClick={next} aria-disabled={!!blockedReason} className={"btn btn-primary " + (blockedReason ? "opacity-70 " : "") + cls}>
        {actionLabel}
      </button>
    ) : (
      <VanButton
        state={van}
        onClick={next}
        disabled={!!blockedReason || phase === "blocked"}
        idleLabel={actionLabel}
        busyText={phase === "verifying" ? tr("co.verifying") : tr("van.placing")}
        error={phase === "error"}
        className={cls}
      />
    );

  return (
    <div className="co-bottom-pad mx-auto max-w-6xl px-4 pt-4 sm:px-6 sm:pt-8">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{tr("checkout.meta")}</h1>
        <Link href="/cart" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-accent">
          <Icon name="left" size={16} /> {tr("nav.cart")}
        </Link>
      </div>

      <div className="mb-5 sm:mb-8">
        <Stepper step={step} reached={placed ? -1 : reached} onGo={(i) => phase !== "verifying" && phase !== "placing" && go(i)} />
      </div>

      <div className="grid gap-6 md:grid-cols-[1fr_340px] lg:grid-cols-[1fr_380px]">
        <div className="min-w-0 space-y-4">
          {!user && <QuickSignIn />}

          <section key={step} className={"co-panel " + (!user ? "pointer-events-none opacity-50" : "")} aria-labelledby="step-h" inert={!user}>
            <h2 id="step-h" ref={headRef} tabIndex={-1} className="mb-3 flex items-center gap-2 text-lg font-extrabold outline-none">
              <span className="chip-icon is-active">
                <Icon name={["pin", "truck", "wallet", "receipt"][step]} size={18} />
              </span>
              {tr((["co.h.address", "co.h.delivery", "co.h.payment", "co.h.review"] as const)[step])}
            </h2>

            {step === 0 && <AddressStep key={user?.phone ?? "guest"} selected={address?.id ?? null} onSelect={setAddrPick} />}
            {step === 1 && (
              <>
                <DeliveryStep t={t} coupon={coupon} />
                <UnboxingToggle checked={unboxing} onChange={setUnboxing} shipments={t.groups.length} disabled={phase === "placing" || phase === "done"} />
              </>
            )}
            {step === 2 && (
              <>
                <PaymentStep
                  value={emi ? null : payment}
                  onChange={(id) => {
                    setEmi(null);
                    setPayment(id);
                  }}
                  total={t.total}
                />
                <div className="mt-3">
                  <EmiOption
                    amount={t.total}
                    value={emi}
                    onChange={(v) => {
                      setEmi(v);
                      if (v) setPayment("card");
                    }}
                  />
                </div>
              </>
            )}
            {step === 3 && (
              <>
              {pointsBalance >= 100 && (
                <label className={"co-option mb-3 items-center " + (usePoints ? "is-active" : "")}>
                  <input
                    type="checkbox"
                    className="sr-only"
                    checked={usePoints}
                    disabled={redeemable.points === 0 || phase === "placing" || phase === "done"}
                    onChange={(e) => setUsePoints(e.target.checked)}
                  />
                  <span className="chip-icon shrink-0">
                    <Icon name="spark" size={18} />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-bold">{tr("co.usePoints", { count: redeemable.points })}</span>
                    <span className="block text-xs text-ink-2">
                      {tr("co.pointsSave", { amount: bdt(redeemable.valueBdt), count: pointsBalance })}
                    </span>
                  </span>
                  <span className={"rounded-full px-2.5 py-1 text-[11px] font-extrabold " + (usePoints ? "bg-accent text-white" : "bg-surface-2 text-ink-2")}>
                    {usePoints ? tr("f.on") : tr("f.off")}
                  </span>
                </label>
              )}
              <Review
                t={t}
                addressLine={address ? `${address.name}, ${address.phone} · ${addressText(address, lang)}` : tr("co.noAddress")}
                paymentText={emi ? tr("co.cardEmi", { count: emi.tenure }) : payment ? paymentLabel(payment, t.total, tx) : tr("co.notChosen")}
                paymentIcon={PAYMENTS.find((p) => p.id === payment)?.icon ?? "wallet"}
                onEdit={go}
                terms={terms}
                setTerms={setTerms}
                lock={phase === "verifying" || phase === "placing" || phase === "done"}
                coupon={coupon}
              />
              <div className="mt-3">
                <CheckoutMoney
                  items={items}
                  coupon={coupon?.code}
                  redeemPoints={usePoints ? redeemable.points : 0}
                  payment={payment}
                  emi={emi}
                  useWallet={useWallet}
                  setUseWallet={setUseWallet}
                  onQuote={setMoneyQ}
                  lock={phase === "verifying" || phase === "placing" || phase === "done"}
                />
              </div>
              </>
            )}

            {step === 3 && phase !== "idle" && (
              <div ref={panelRef} className="mt-4 scroll-mb-48">
                <VerifyPanel states={checks} error={error} changed={changedList} failed={failed} onRetry={next} />
              </div>
            )}

            {nudge && (
              <p key={nudge.n} role="alert" className="shake mt-3 flex items-center gap-2 rounded-2xl bg-warn/10 p-3 text-sm font-semibold text-warn">
                <Icon name="info" size={16} /> {nudge.msg}
              </p>
            )}
          </section>
        </div>

        <aside className="hidden md:block">
          <div className="sticky top-24 space-y-4">
            <section className="co-card space-y-4 p-5" aria-labelledby="co-sum-h">
              <h2 id="co-sum-h" className="text-base font-extrabold">
{tr("cart.summary")}
</h2>
              <ul className="space-y-2 text-xs text-ink-2">
                {t.groups.map((g) => (
                  <li key={g.market} data-store={g.market} className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-accent" />
                    <span className="flex-1">
                      {tr("del.fromLine", { source: STORES[g.market].source, items: tr("cart.items", { count: g.count }) })}
                    </span>
                    <span className="font-semibold tabular-nums text-ink">{bdt(g.sub)}</span>
                  </li>
                ))}
              </ul>
              <SummaryLines t={t} coupon={coupon} perStore />
              {action("w-full")}
              {step > 0 && phase === "idle" && (
                <button type="button" onClick={() => go(step - 1)} className="btn btn-ghost w-full">
                  <Icon name="left" size={16} /> {tr("co.backTo", { step: tr(STEPS[step - 1].label).toLowerCase() })}
                </button>
              )}
              <TrustRow />
            </section>
            <DeliveryPromise />
          </div>
        </aside>
      </div>

      {/* mobile sticky action bar above the bottom nav */}
      <div className="co-bottom-bar fixed inset-x-0 bottom-16 z-40 mb-[env(safe-area-inset-bottom)] px-4 pt-3 md:hidden">
        <div className="mx-auto flex max-w-md items-center gap-2">
          {step > 0 && phase === "idle" ? (
            <button type="button" onClick={() => go(step - 1)} className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-surface-2" aria-label={tr("co.backTo", { step: tr(STEPS[step - 1].label) })}>
              <Icon name="left" size={18} />
            </button>
          ) : null}
          <div className="min-w-0 shrink-0 pr-1">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-3">{tr("sum.total")}</p>
            <p key={t.total} className="co-bump text-base font-extrabold leading-tight tabular-nums">
              {bdt(step === 3 && moneyQ ? moneyQ.payNow : t.total)}
</p>
          </div>
          {action("min-w-0 flex-1 px-3 text-sm")}
        </div>
      </div>
    </div>
  );
}

function Review({
  t,
  addressLine,
  paymentText,
  paymentIcon,
  onEdit,
  terms,
  setTerms,
  lock,
  coupon,
}: {
  coupon: ReturnType<typeof useCoupon>[0];
  t: ReturnType<typeof computeTotals>;
  addressLine: string;
  paymentText: string;
  paymentIcon: string;
  onEdit: (i: number) => void;
  terms: boolean;
  setTerms: (v: boolean) => void;
  lock: boolean;
}) {
  const { t: tr, bdt } = useT();
  return (
    <div className="space-y-3">
      {t.groups.map((g) => (
        <section key={g.market} data-store={g.market} className="co-card overflow-hidden">
          <header className="co-store-head flex items-center gap-3 px-4 py-3">
            <span className="chip-icon is-active shrink-0">
              <Icon name={STORES[g.market].icon} size={16} />
            </span>
            <h3 className="flex-1 text-sm font-extrabold">{tr("cart.fromSource", { source: STORES[g.market].source })}</h3>
            <span className={"text-xs font-bold " + (g.shipping === 0 ? "text-good" : "text-ink-2")}>
              {g.shipping === 0 ? tr("co.shippingFree") : tr("co.shippingAmt", { amount: bdt(g.shipping) })}
            </span>
          </header>
          <ul className="divide-y divide-line/60">
            {g.items.map((i) => (
              <li key={i.productId + (i.variant ?? "")} className="flex items-center gap-3 px-4 py-2.5">
                <span className="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white p-1">
                  <ProductImage p={i} className="h-full w-full" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="line-clamp-1 text-sm font-medium">{i.title}</span>
                  <span className="text-xs text-ink-2">
                    {i.variant ? i.variant + " · " : ""}{tr("co.qty", { qty: i.qty })}
                  </span>
                </span>
                <span className="text-sm font-bold tabular-nums">{bdt(i.unitBdt * i.qty)}</span>
              </li>
            ))}
          </ul>
        </section>
      ))}

      {[
        { icon: "pin", title: tr("co.deliverTo"), text: addressLine, step: 0 },
        { icon: paymentIcon, title: tr("co.payment"), text: paymentText, step: 2 },
      ].map((r) => (
        <div key={r.title} className="co-option cursor-default items-center">
          <span className="chip-icon shrink-0">
            <Icon name={r.icon} size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-xs font-semibold text-ink-3">{r.title}</span>
            <span className="block text-sm font-medium">{r.text}</span>
          </span>
          {!lock && (
            <button type="button" onClick={() => onEdit(r.step)} className="h-11 shrink-0 rounded-full px-3 text-sm font-bold text-accent hover:bg-accent/10">
              {tr("co.change")}<span className="sr-only"> {r.title.toLowerCase()}</span>
            </button>
          )}
        </div>
      ))}

      <div className="co-card p-4 md:hidden">
        <SummaryLines t={t} coupon={coupon} perStore />
      </div>

      <CheckoutRestrictions items={t.groups.flatMap((g) => g.items)} />

      <label className={"co-option items-center " + (terms ? "is-active" : "")}>
        <input type="checkbox" className="sr-only" checked={terms} disabled={lock} onChange={(e) => setTerms(e.target.checked)} />
        <span className={"grid h-6 w-6 shrink-0 place-items-center rounded-md transition-colors " + (terms ? "bg-accent text-white" : "bg-surface-2 text-transparent")}>
          {terms && (
            <svg viewBox="0 0 24 24" width="16" height="16" className="draw-check" aria-hidden>
              <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          )}
        </span>
        <span className="text-xs leading-relaxed text-ink-2">
          {rich(tr("co.agree"), {
  terms: (
    <Link href="/legal/terms" target="_blank" className="font-semibold text-accent underline underline-offset-2">
      {tr("co.terms")}
    </Link>
  ),
  refund: (
    <Link href="/legal/refunds" target="_blank" className="font-semibold text-accent underline underline-offset-2">
      {tr("co.refundPolicy")}
    </Link>
  ),
  shipping: (
    <Link href="/legal/shipping" target="_blank" className="font-semibold text-accent underline underline-offset-2">
      {tr("co.shippingPolicy")}
    </Link>
  ),
})}
        </span>
      </label>
    </div>
  );
}

function CheckoutSkeleton() {
  const { t: tr } = useT();
  return (
    <div className="mx-auto max-w-6xl px-4 pt-6 sm:px-6" aria-busy="true" aria-label={tr("co.loading")}>
      <div className="skeleton mb-6 h-8 w-40 rounded-xl" />
      <div className="skeleton mb-8 h-12 rounded-full" />
      <div className="grid gap-6 md:grid-cols-[1fr_340px]">
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="skeleton h-24 rounded-2xl" />
          ))}
        </div>
        <div className="skeleton hidden h-80 rounded-[20px] md:block" />
      </div>
    </div>
  );
}
