"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Order } from "@/lib/types";
import { api, ApiError, apiEnabled } from "@/lib/apiClient";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { deliveryWindow, formatBdt, groupByMarket } from "@/lib/pricing";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";
import { addDays } from "./cart-math";
import { TellAFriend } from "@/components/share/TellAFriend";

const CONFETTI = [
  [-90, -60, "var(--brand-2)"],
  [80, -70, "var(--accent)"],
  [-120, 10, "var(--accent-2)"],
  [115, 20, "var(--brand-2)"],
  [-50, -95, "var(--good)"],
  [45, -100, "var(--accent-2)"],
  [-20, 40, "var(--accent)"],
  [140, -30, "var(--good)"],
] as const;

export function OrderSuccess({ orderId, payStatus }: { orderId: string; payStatus?: "paid" | "failed" | "cancelled" }) {
  const { orders, ready, user } = useStore();
  const [retrying, setRetrying] = useState(false);
  // After a gateway redirect the local order list can be stale (or the order was placed on another device):
  // fetch it from the server so the shopper always sees their order.
  const local = orders.find((o) => o.id === orderId);
  const [remote, setRemote] = useState<Order | null>(null);
  const [lookup, setLookup] = useState<"idle" | "loading" | "done">("idle");
  useEffect(() => {
    if (!ready || local || !orderId || !apiEnabled || !user) return;
    let alive = true;
    setLookup("loading");
    api
      .order(orderId)
      .then((r) => alive && setRemote(r.order as Order))
      .catch(() => {})
      .finally(() => alive && setLookup("done"));
    return () => {
      alive = false;
    };
  }, [ready, local, orderId, user]);
  const [retryError, setRetryError] = useState<string | null>(null);

  if (!ready || lookup === "loading") {
    return (
      <div className="mx-auto max-w-xl px-4 pt-10" aria-busy="true" aria-label="Loading your order">
        <div className="skeleton mx-auto h-44 w-44 rounded-full" />
        <div className="skeleton mx-auto mt-6 h-8 w-64 rounded-xl" />
        <div className="skeleton mt-8 h-40 rounded-[20px]" />
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
          Payment received
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          Thank you! Order <b className="text-ink">{orderId}</b> is confirmed. We&apos;ve sent the details by SMS. Sign in with the same phone number
          to track it.
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Link href={`/login?next=${encodeURIComponent(`/orders/${orderId}`)}`} className="btn btn-primary">
            <Icon name="receipt" size={18} /> Sign in to track
          </Link>
          <Link href="/" className="btn btn-ghost">
            Continue shopping
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
          We couldn&apos;t find that order
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          {orderId ? (
            <>
              No order <b className="text-ink">{orderId}</b> is saved in this browser. If you placed it on another device, sign in there or check
              your orders list.
            </>
          ) : (
            "This link doesn't include an order number."
          )}
        </p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Link href="/orders" className="btn btn-primary">
            <Icon name="receipt" size={18} /> My orders
          </Link>
          <Link href="/" className="btn btn-ghost">
            Continue shopping
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
        if (res.payment.redirectUrl) window.location.assign(res.payment.redirectUrl);
        else window.location.assign(`/checkout/success?order=${order.id}&status=paid`);
      } catch (e) {
        setRetryError(e instanceof ApiError ? e.message : "Couldn't reach the payment gateway. Please try again.");
        setRetrying(false);
      }
    };
    return (
      <section className="fade-in mx-auto max-w-md px-4 py-14 text-center" aria-labelledby="pay-h">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl" style={{ background: "color-mix(in oklab, var(--bad) 14%, transparent)", color: "var(--bad)" }}>
          <Icon name="wallet" size={26} />
        </span>
        <h1 id="pay-h" className="mt-4 text-2xl font-extrabold tracking-tight">
          Payment {payStatus === "cancelled" ? "cancelled" : "not completed"}
        </h1>
        <p className="mt-2 text-sm text-ink-2">
          Order <b className="text-ink">{order.id}</b> is saved, but nothing was charged. We will start buying your items in India as soon as the
          payment goes through.
        </p>
        <p role="status" aria-live="polite" className="mt-3 min-h-5 text-sm font-semibold text-bad">
          {retryError}
        </p>
        <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <button type="button" className="btn btn-primary" onClick={retry} disabled={retrying} aria-busy={retrying}>
            <Icon name="wallet" size={18} /> {retrying ? "Opening payment..." : "Try payment again"}
          </button>
          <Link href={`/orders/${order.id}`} className="btn btn-ghost">
            View order
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
        <div className="relative mx-auto h-48 w-56" aria-hidden="true">
          <div className="co-confetti absolute inset-0">
            {CONFETTI.map(([x, y, c], i) => (
              <i key={i} style={{ ["--x" as string]: `${x}px`, ["--y" as string]: `${y}px`, background: c, animationDelay: `${1.15 + i * 0.03}s` }} />
            ))}
          </div>
          <svg viewBox="0 0 220 190" className="absolute inset-0 h-full w-full overflow-visible">
            <path className="co-ground" d="M40 176h140" stroke="var(--ink-3)" strokeOpacity=".35" strokeWidth="3" strokeLinecap="round" />
            <g className="parachute">
              <path d="M62 66a48 38 0 0 1 96 0z" fill="var(--accent)" />
              <path d="M86 66a24 38 0 0 1 48 0" fill="var(--accent-2)" opacity=".55" />
              <path d="M64 66l36 70M156 66l-36 70M110 66v70" stroke="var(--ink-3)" strokeWidth="1.5" />
              <rect x="88" y="134" width="44" height="40" rx="7" fill="var(--brand-2)" />
              <path d="M88 148h44M110 134v14" stroke="#fff" strokeOpacity=".55" strokeWidth="2" />
            </g>
          </svg>
          <span className="co-success-check absolute -right-1 bottom-2 grid h-14 w-14 place-items-center rounded-full bg-good text-white shadow-[0_10px_28px_color-mix(in_srgb,var(--good)_40%,transparent)]">
            <svg viewBox="0 0 24 24" width="28" height="28" className="draw-check">
              <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </span>
        </div>

        <div role="status" aria-live="polite">
          <h1 id="ok-h" className="rise mt-4 text-2xl font-extrabold tracking-tight sm:text-3xl" style={{ ["--i" as string]: 4 }}>
            Order placed, {order.address.name.split(" ")[0]}!
          </h1>
          <p className="rise mt-2 text-sm text-ink-2" style={{ ["--i" as string]: 6 }}>
            Order <b className="font-mono text-ink">{order.id}</b> · {count} item{count === 1 ? "" : "s"} · {formatBdt(order.total)}
          </p>
        </div>
      </section>

      <section className="co-card rise mt-6 overflow-hidden" style={{ ["--i" as string]: 8 }} aria-label="Order details">
        <div className="grid grid-cols-2 gap-px bg-surface-2">
          {[
            ["receipt", "Total", formatBdt(order.total)],
            ["clock", "Arrives", `${addDays(order.createdAt, w.min)} – ${addDays(order.createdAt, w.max)}`],
            ["wallet", "Payment", order.payment],
            ["truck", "Tracking", order.trackingCode],
          ].map(([i, k, v]) => (
            <div key={k} className="flex items-start gap-2.5 bg-surface p-3.5">
              <span className="chip-icon h-8 w-8 shrink-0 rounded-lg">
                <Icon name={i} size={16} />
              </span>
              <span className="min-w-0">
                <span className="block text-[11px] font-semibold uppercase tracking-wide text-ink-3">{k}</span>
                <span className="block break-words text-sm font-bold">{v}</span>
              </span>
            </div>
          ))}
        </div>
        <div className="space-y-1.5 p-4 text-xs text-ink-2">
          <p className="flex items-start gap-2">
            <Icon name="pin" size={15} className="mt-px shrink-0 text-accent" />
            <span>
              Delivering to <b className="text-ink">{order.address.name}</b>, {[order.address.line, order.address.area, order.address.district].join(", ")}
            </span>
          </p>
          {markets.map((m) => (
            <p key={m} data-store={m} className="flex items-center gap-2">
              <span className="ml-1 h-2 w-2 shrink-0 rounded-full bg-accent" />
              {groups[m]!.reduce((n, i) => n + i.qty, 0)} item(s) bought from {STORES[m].source}, India
            </p>
          ))}
        </div>
      </section>

      <section className="co-card rise mt-4 p-4" style={{ ["--i" as string]: 10 }} aria-labelledby="next-h">
        <h2 id="next-h" className="text-sm font-extrabold">
          What happens next
        </h2>
        <ol className="mt-3 space-y-3">
          {[
            ["shield", "We buy your items in India", "Our team purchases from the store within 24 hours and sends you an SMS."],
            ["plane", "Skydrop carries it across", "From the Kolkata hub to Bangladesh, cleared through customs."],
            ["home", "Delivered to your door", `Expected in ${w.min}-${w.max} days. The rider calls before arriving.`],
          ].map(([i, a, b], n) => (
            <li key={a} className="flex gap-3">
              <span className={"chip-icon shrink-0 " + (n === 0 ? "is-active" : "")}>
                <Icon name={i} size={17} />
              </span>
              <span>
                <span className="block text-sm font-bold">
                  {a} {n === 0 && <span className="ml-1 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase text-accent">Up next</span>}
                </span>
                <span className="block text-xs text-ink-2">{b}</span>
              </span>
            </li>
          ))}
        </ol>
      </section>

      <TellAFriend style={{ ["--i" as string]: 11 }} />

      <div className="rise mt-6 flex flex-col gap-2 sm:flex-row" style={{ ["--i" as string]: 12 }}>
        <Link href={`/orders/${order.id}`} className="btn btn-primary flex-1">
          <Icon name="truck" size={18} /> Track order
        </Link>
        <Link href="/" className="btn btn-ghost flex-1">
          Continue shopping <Icon name="arrow" size={18} className="arrow" />
        </Link>
      </div>
    </div>
  );
}
