"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Order } from "@/lib/types";
import { api, ApiError, apiEnabled, goToPayment } from "@/lib/apiClient";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow, groupByMarket } from "@/lib/pricing";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";
import { addDays } from "./cart-math";
import { TellAFriend } from "@/components/share/TellAFriend";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import { bdName } from "@/lib/bd";


export function OrderSuccess({ orderId, payStatus }: { orderId: string; payStatus?: "paid" | "failed" | "cancelled" }) {
  const { orders, ready, user } = useStore();
  const { t, bdt, lang } = useT();
  const [retrying, setRetrying] = useState(false);
  // After a gateway redirect the local order list can be stale (or the order was placed on another device):
  // fetch it from the server so the shopper always sees their order.
  const local = orders.find((o) => o.id === orderId);
  const [remote, setRemote] = useState<Order | null>(null);
  const [lookedUp, setLookedUp] = useState(false);
  const needsLookup = ready && !local && !!orderId && apiEnabled && !!user;
  const lookup = needsLookup && !lookedUp ? "loading" : "done";
  useEffect(() => {
    if (!needsLookup) return;
    let alive = true;
    api
      .order(orderId)
      .then((r) => alive && setRemote(r.order as Order))
      .catch(() => {})
      .finally(() => alive && setLookedUp(true));
    return () => {
      alive = false;
    };
  }, [needsLookup, orderId]);
  const [retryError, setRetryError] = useState<string | null>(null);

  if (!ready || lookup === "loading") {
    return (
      <div className="mx-auto max-w-xl px-4 pt-10" aria-busy="true" aria-label={t("os.loading")}>
        <div className="skeleton mx-auto h-44 w-44 rounded-full" />
        <div className="skeleton mx-auto mt-6 h-8 w-64 rounded-xl" />
        <div className="skeleton mt-8 h-40 rounded-[12px]" />
      </div>
    );
  }

  const order = local ?? remote;

  if (!order && payStatus === "paid" && orderId) {
    // Paid, but this browser isn't signed in to the account that ordered: confirm the payment anyway.
    return (
      <section className="fade-in mx-auto max-w-md px-4 py-16 text-center" aria-labelledby="pd-h">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl" style={{ background: "color-mix(in oklab, var(--good) 14%, transparent)", color: "var(--good)" }}>
          <Icon name="check" size={26} />
        </span>
        <h1 id="pd-h" className="mt-4 text-2xl font-extrabold tracking-tight">
          {t("os.paidTitle")}
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          {rich(t("os.paidBody"), { id: <b className="text-ink">{orderId}</b> })}
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Link href={`/login?next=${encodeURIComponent(`/orders/${orderId}`)}`} className="btn btn-primary">
            <Icon name="receipt" size={18} /> {t("os.signInTrack")}
          </Link>
          <Link href="/" className="btn btn-ghost">
            {t("os.continue")}
          </Link>
        </div>
      </section>
    );
  }

  if (!order && (payStatus === "failed" || payStatus === "cancelled") && orderId) {
    // Payment failed, but this browser can't see the order (signed out or another device): never a dead end.
    return (
      <section className="fade-in mx-auto max-w-md px-4 py-14 text-center" aria-labelledby="pay-h">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl" style={{ background: "color-mix(in oklab, var(--bad) 14%, transparent)", color: "var(--bad)" }}>
          <Icon name="wallet" size={26} />
        </span>
        <h1 id="pay-h" className="mt-4 text-2xl font-extrabold tracking-tight">
          {payStatus === "cancelled" ? t("os.payCancelled") : t("os.payIncomplete")}
        </h1>
        <p className="mt-2 text-sm text-ink-2">{rich(t("os.savedNothing"), { id: <b className="text-ink">{orderId}</b> })}</p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Link href={`/login?next=${encodeURIComponent(`/orders/${orderId}`)}`} className="btn btn-primary">
            <Icon name="wallet" size={18} /> {t("os.signInPay")}
          </Link>
          <Link href="/" className="btn btn-ghost">
            {t("os.continue")}
          </Link>
        </div>
      </section>
    );
  }

  if (!order) {
    return (
      <section className="fade-in mx-auto max-w-md px-4 py-16 text-center" aria-labelledby="nf-h">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl">
          <Icon name="receipt" size={26} />
        </span>
        <h1 id="nf-h" className="mt-4 text-2xl font-extrabold tracking-tight">
          {t("os.nfTitle")}
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          {orderId ? (
            <>
              {rich(t("os.nfBody"), { id: <b className="text-ink">{orderId}</b> })}
            </>
          ) : (
            t("os.noId")
          )}
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Link href="/orders" className="btn btn-primary">
            <Icon name="receipt" size={18} /> {t("os.myOrders")}
          </Link>
          <Link href="/" className="btn btn-ghost">
            {t("os.continue")}
          </Link>
        </div>
      </section>
    );
  }

  if (payStatus === "failed" || payStatus === "cancelled") {
    const retry = async () => {
      setRetrying(true);
      setRetryError(null);
      try {
        const res = await api.payOrder(order.id, `${window.location.origin}/checkout/success`);
        if (res.payment.redirectUrl) goToPayment(res.payment.redirectUrl);
        else window.location.assign(`/checkout/success?order=${order.id}&status=paid`);
      } catch (e) {
        setRetryError(e instanceof ApiError ? e.message : t("os.gateway"));
        setRetrying(false);
      }
    };
    return (
      <section className="fade-in mx-auto max-w-md px-4 py-14 text-center" aria-labelledby="pay-h">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl" style={{ background: "color-mix(in oklab, var(--bad) 14%, transparent)", color: "var(--bad)" }}>
          <Icon name="wallet" size={26} />
        </span>
        <h1 id="pay-h" className="mt-4 text-2xl font-extrabold tracking-tight">
          {payStatus === "cancelled" ? t("os.payCancelled") : t("os.payIncomplete")}
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          {rich(t("os.savedNothing"), { id: <b className="text-ink">{order.id}</b> })}
        </p>
        <p role="status" aria-live="polite" className="mt-3 min-h-5 text-sm font-semibold text-bad">
          {retryError}
        </p>
        <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <button type="button" className="btn btn-primary" onClick={retry} disabled={retrying} aria-busy={retrying}>
            <Icon name="wallet" size={18} /> {retrying ? t("os.opening") : t("os.retryPay")}
          </button>
          <Link href={`/orders/${order.id}`} className="btn btn-ghost">
            {t("os.viewOrder")}
          </Link>
        </div>
      </section>
    );
  }

  const w = deliveryWindow();
  const groups = groupByMarket(order.items);
  const markets = Object.keys(groups) as MarketId[];
  const count = order.items.reduce((n, i) => n + i.qty, 0);

  return (
    <div className="co-bottom-pad mx-auto max-w-xl px-4 pt-6 sm:pt-10">
      <section className="text-center" aria-labelledby="ok-h">
        <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-[color-mix(in_srgb,var(--good)_12%,transparent)] text-good" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="32" height="32" className="draw-check">
            <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </div>

        <div role="status" aria-live="polite">
          <h1 id="ok-h" className="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
            {t("os.placed", { name: order.address.name.split(" ")[0] })}
          </h1>
          <p className="mt-2 text-sm text-ink-2">
            {rich(t("os.line", { items: t("cart.items", { count }), total: bdt(order.total) }), { id: <b className="font-mono text-ink">{order.id}</b> })}
          </p>
        </div>
      </section>

      <section className="co-card mt-6 overflow-hidden" aria-label={t("os.details")}>
        <div className="grid grid-cols-2 gap-px bg-surface-2">
          {[
            ["receipt", t("os.total"), bdt(order.total)],
["clock", t("os.arrives"), `${addDays(order.createdAt, w.min, lang)} – ${addDays(order.createdAt, w.max, lang)}`],
["wallet", t("os.payment"), order.payment],
["truck", t("os.tracking"), order.trackingCode],
          ].map(([i, k, v]) => (
            <div key={k} className="flex items-start gap-2.5 bg-surface p-3.5">
              <span className="chip-icon h-8 w-8 shrink-0 rounded-lg">
                <Icon name={i} size={16} />
              </span>
              <span className="min-w-0">
                <span className="block text-[11px] font-semibold uppercase tracking-wide text-ink-3">{k}</span>
                <span className={"block break-words font-bold " + (k === t("os.tracking") ? "text-[13px] tabular-nums tracking-tight" : "text-sm")}>{v}</span>
              </span>
            </div>
          ))}
        </div>
        <div className="space-y-1.5 p-4 text-xs text-ink-2">
          <p className="flex items-start gap-2">
            <Icon name="pin" size={15} className="mt-px shrink-0 text-accent" />
            <span>
              {rich(t("os.deliveringTo", { address: [order.address.line, order.address.area, bdName(order.address.district, lang)].join(", ") }), { name: <b className="text-ink">{order.address.name}</b> })}
            </span>
          </p>
          {markets.map((m) => (
            <p key={m} data-store={m} className="flex items-center gap-2">
              <span className="ml-1 h-2 w-2 shrink-0 rounded-full bg-accent" />
              {t("os.boughtFrom", { count: groups[m]!.reduce((n, i) => n + i.qty, 0), source: STORES[m].source })}
            </p>
          ))}
        </div>
      </section>

      <section className="co-card mt-4 p-4" aria-labelledby="next-h">
        <h2 id="next-h" className="text-sm font-extrabold">
          {t("os.next")}
        </h2>
        <ol className="mt-3 space-y-3">
          {[
            ["shield", t("os.n1"), t("os.n1b")],
["plane", t("os.n2"), t("os.n2b")],
["home", t("os.n3"), t("os.n3b", { min: w.min, max: w.max })],
          ].map(([i, a, b], n) => (
            <li key={a} className="flex gap-3">
              <span className={"chip-icon shrink-0 " + (n === 0 ? "is-active" : "")}>
                <Icon name={i} size={17} />
              </span>
              <span>
                <span className="block text-sm font-bold">
                  {a} {n === 0 && <span className="ml-1 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase text-accent">{t("os.upNext")}</span>}
                </span>
                <span className="block text-xs text-ink-2">{b}</span>
              </span>
            </li>
          ))}
        </ol>
      </section>

      <TellAFriend style={{ ["--i" as string]: 11 }} />

      <div className="mt-6 flex flex-col gap-2 sm:flex-row">
        <Link href={`/orders/${order.id}`} className="btn btn-primary flex-1">
          <Icon name="truck" size={18} /> {t("os.track")}
        </Link>
        <Link href="/" className="btn btn-ghost flex-1">
          {t("os.continue")} <Icon name="arrow" size={18} className="arrow" />
        </Link>
      </div>
    </div>
  );
}
