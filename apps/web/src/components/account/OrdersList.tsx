"use client";

import { useState } from "react";
import Link from "next/link";
import type { Order, OrderStatus } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { formatBdt } from "@/lib/pricing";
import { currentStatus, etaRange, isInProgress, storesOf } from "@/lib/orderStatus";
import { EmptyState, LoadingBlock, PageHead, SegTabs, useNow } from "./ui";
import { ProgressBar, StatusChip, Thumb, orderDate, shortId } from "./OrderBits";

type Tab = "all" | "active" | "delivered" | "cancelled";

const MATCH: Record<Tab, (s: OrderStatus) => boolean> = {
  all: () => true,
  active: isInProgress,
  delivered: (s) => s === "delivered",
  cancelled: (s) => s === "cancelled",
};

const EMPTY: Record<Tab, { title: string; body: string }> = {
  all: { title: "No orders yet", body: "Find something you love from India. We buy it there and Skydrop brings it to your door." },
  active: { title: "Nothing on the way", body: "Orders being bought, shipped or delivered show up here." },
  delivered: { title: "No deliveries yet", body: "Delivered orders will appear here once your first parcel arrives." },
  cancelled: { title: "No cancelled orders", body: "Good news: every order is still going." },
};

export function OrdersList({ initialTab }: { initialTab: Tab }) {
  const { ready, user, orders } = useStore();
  const now = useNow(30_000);
  const [tab, setTab] = useState<Tab>(initialTab);

  const withStatus = orders.map((o) => ({ o, s: currentStatus(o, now) }));
  const count = (t: Tab) => withStatus.filter((x) => MATCH[t](x.s)).length;
  const shown = withStatus.filter((x) => MATCH[tab](x.s));

  function change(t: Tab) {
    setTab(t);
    const url = new URL(window.location.href);
    if (t === "all") url.searchParams.delete("tab");
    else url.searchParams.set("tab", t);
    window.history.replaceState(null, "", url);
  }

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title="Your orders" sub="Bought in India, delivered by Skydrop" icon="receipt" />

      {ready && !user && (
        <div className="card rise mb-4 flex items-center gap-3 p-3 pl-4">
          <span className="chip-icon shrink-0">
            <Icon name="user" size={18} />
          </span>
          <p className="min-w-0 flex-1 text-sm text-ink-2">
            <span className="font-semibold text-ink">Sign in</span> to keep your orders safe and get delivery texts.
            {orders.length > 0 && " Orders placed on this device are shown below."}
          </p>
          <Link href="/login?next=/orders" className="btn btn-primary h-11 shrink-0 px-4 text-sm">
            Sign in
          </Link>
        </div>
      )}

      <SegTabs<Tab>
        label="Filter orders"
        dense
        controls="orders-panel"
        value={tab}
        onChange={change}
        items={[
          { id: "all", label: "All", icon: "grid", count: count("all") },
          { id: "active", label: "Active", icon: "truck", count: count("active") },
          { id: "delivered", label: "Done", icon: "check", count: count("delivered") },
          { id: "cancelled", label: "Cancelled", icon: "x", count: count("cancelled") },
        ]}
      />

      <div id="orders-panel" role="tabpanel" aria-labelledby={`tab-${tab}`} className="mt-4">
        <p className="sr-only" aria-live="polite">
          {ready ? `${shown.length} order${shown.length === 1 ? "" : "s"}` : ""}
        </p>
        {!ready ? (
          <LoadingBlock rows={3} />
        ) : shown.length === 0 ? (
          <EmptyState {...EMPTY[tab]} action={orders.length ? { label: "See all orders", onClick: () => change("all") } : { label: "Start shopping", href: "/" }} />
        ) : (
          <ul className="space-y-3">
            {shown.map(({ o, s }, i) => (
              <li key={o.id + tab} className="rise" style={{ ["--i" as string]: i }}>
                <OrderCard o={o} s={s} />
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function OrderCard({ o, s }: { o: Order; s: OrderStatus }) {
  const stores = storesOf(o);
  const items = o.items.reduce((n, i) => n + i.qty, 0);
  const thumbs = o.items.slice(0, 3);
  return (
    <Link
      href={`/orders/${encodeURIComponent(o.id)}`}
      className="card group block p-4 transition-transform hover:-translate-y-0.5 hover:shadow-[var(--shadow-2)]"
    >
      <div className="flex items-start gap-3">
        <div className="ac-stack" aria-hidden>
          {thumbs.map((it, i) => (
            <span key={it.productId + (it.variant ?? "")} style={{ ["--i" as string]: i, zIndex: 3 - i }} className="rounded-xl shadow-[var(--shadow-1)]">
              <Thumb item={it} size={48} />
            </span>
          ))}
          {o.items.length > 3 && (
            <span className="grid h-[52px] w-[52px] place-items-center rounded-xl bg-surface-2 text-xs font-bold text-ink-2">
              +{o.items.length - 3}
            </span>
          )}
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <p className="truncate font-bold tabular-nums">{shortId(o.id)}</p>
            <Icon name="right" size={18} className="ac-chev shrink-0" />
          </div>
          <p className="text-xs text-ink-2">
            {orderDate(o.createdAt)} · {String(items).padStart(2, "0")} item{items === 1 ? "" : "s"}
          </p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {stores.map((m) => (
              <span key={m} data-store={m} className="ac-chip h-6 text-[11px]">
                <Icon name={STORES[m].icon} size={11} /> {STORES[m].source}
              </span>
            ))}
          </div>
        </div>
      </div>
      <div className="mt-3 flex items-center justify-between gap-2">
        <StatusChip status={s} />
        <span className="text-lg font-extrabold tabular-nums tracking-tight">{formatBdt(o.total)}</span>
      </div>
      <div className="mt-2.5">
        <ProgressBar status={s} label={`Progress for order ${shortId(o.id)}`} />
        {isInProgress(s) && (
          <p className="mt-1.5 flex items-center gap-1 text-[11px] text-ink-2">
            <Icon name="clock" size={12} /> Expected {etaRange(o)}
          </p>
        )}
      </div>
    </Link>
  );
}
