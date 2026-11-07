"use client";

import { useState } from "react";
import Link from "next/link";
import type { MarketId, Order, OrderStatus } from "@/lib/types";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { formatBdt, groupByMarket } from "@/lib/pricing";
import { STAGES, canCancel, currentStatus, etaRange, isInProgress, stageIndex, stageLabel } from "@/lib/orderStatus";
import { ConfirmDialog, EmptyState, LoadingBlock, PageHead, useNow } from "./ui";
import { ProgressBar, StatusChip, Thumb, orderDate, shortId } from "./OrderBits";
import { prettyPhone } from "./phone";
import { SafeSection } from "@/components/resilience/SafeSection";
import { TrackingPanel } from "@/components/fulfilment/TrackingPanel";
import { OrderCases } from "@/components/fulfilment/OrderCases";

const TRACK_URL = "https://track.skydrop.online";

export function OrderDetail({ id }: { id: string }) {
  const { ready, orders, cancelOrder } = useStore();
  const toast = useToast();
  // Re-render every 30 s so the simulated (demo) status moves along.
  const now = useNow(30_000);
  const [confirm, setConfirm] = useState(false);

  const order = orders.find((o) => o.id === id);

  if (!ready || !now)
    return (
      <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-12">
        <LoadingBlock rows={3} />
      </div>
    );

  if (!order)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
        <PageHead title="Order not found" icon="receipt" back={{ href: "/orders", label: "All orders" }} />
        <EmptyState
          title="We couldn't find this order"
          body="It may have been placed on another device or browser. Orders in this demo are stored on the device you used."
          action={{ label: "See your orders", href: "/orders" }}
        />
      </div>
    );

  const s = currentStatus(order, now);
  const cancellable = canCancel(s);

  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title={`Order ${shortId(order.id)}`} sub={`Placed ${orderDate(order.createdAt)}`} icon="receipt" back={{ href: "/orders", label: "All orders" }} />

      {/* summary + tracking */}
      <section className="card rise p-4 sm:p-5" aria-labelledby="track-h">
        <div className="flex flex-wrap items-center gap-2">
          <h2 id="track-h" className="mr-auto text-lg font-extrabold tracking-tight">
            Tracking
          </h2>
          <StatusChip status={s} />
          {isInProgress(s) && (
            <span className="ac-chip" data-tone="muted">
              <Icon name="clock" size={12} /> ETA {etaRange(order)}
            </span>
          )}
        </div>
        <div className="mt-3">
          <ProgressBar status={s} />
          <p className="mt-1.5 text-xs text-ink-2" aria-live="polite">
            {s === "cancelled"
              ? "This order was cancelled. Any payment is refunded to the original method."
              : s === "delivered"
                ? "Delivered. Thank you for shopping with Skydrop Retail."
                : `Step ${String(stageIndex(s) + 1).padStart(2, "0")} of ${String(STAGES.length).padStart(2, "0")}: ${stageLabel(STAGES[stageIndex(s)], order)}`}
          </p>
        </div>

        {s !== "cancelled" && <Timeline order={order} status={s} />}

        {/* tracking code */}
        <TrackingCode code={order.trackingCode} />
        <p className="mt-2 text-[11px] text-ink-3">Demo: tracking moves forward on its own every few minutes. Live orders update from Skydrop hub scans.</p>
      </section>

      {/* fulfilment: route map + live rider, unboxing approval, returns */}
      <SafeSection label="Route map" quiet>
        <TrackingPanel order={order} status={s} now={now} />
      </SafeSection>
      <SafeSection label="Unboxing and returns">
        <OrderCases order={order} status={s} />
      </SafeSection>

      <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_340px]">
        {/* items by store */}
        <section aria-labelledby="items-h" className="space-y-3">
          <h2 id="items-h" className="sr-only">
            Items
          </h2>
          {(Object.entries(groupByMarket(order.items)) as [MarketId, Order["items"]][]).map(([m, items], gi) => (
            <div key={m} data-store={m} className="card rise overflow-hidden" style={{ ["--i" as string]: gi + 1 }}>
              <div className="flex items-center gap-3 px-4 py-3" style={{ background: "var(--accent-soft)" }}>
                <span className="chip-icon is-active">
                  <Icon name={STORES[m].icon} size={18} />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="font-bold">{STORES[m].name}</p>
                  <p className="text-xs text-ink-2">Bought from {STORES[m].source}, India</p>
                </div>
                <span className="ac-chip tabular-nums">{String(items.reduce((n, i) => n + i.qty, 0)).padStart(2, "0")} items</span>
              </div>
              <ul>
                {items.map((it) => (
                  <li key={it.productId + (it.variant ?? "")} className="ac-row">
                    <Thumb item={it} size={56} />
                    <div className="min-w-0 flex-1">
                      <Link href={`/p/${it.productId}`} className="line-clamp-2 text-sm font-medium hover:text-accent">
                        {it.title}
                      </Link>
                      <p className="text-xs text-ink-2">
                        {it.variant ? it.variant + " · " : ""}Qty {it.qty} × {formatBdt(it.unitBdt)}
                      </p>
                    </div>
                    <span className="shrink-0 font-bold tabular-nums">{formatBdt(it.unitBdt * it.qty)}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </section>

        <aside className="space-y-4">
          <section className="card rise p-4" aria-labelledby="addr-h" style={{ ["--i" as string]: 2 }}>
            <div className="flex items-start gap-3">
              <span className="chip-icon shrink-0">
                <Icon name="pin" size={18} />
              </span>
              <div className="min-w-0">
                <h2 id="addr-h" className="text-sm font-bold">
                  Delivery address
                </h2>
                <p className="mt-1 text-sm font-semibold">{order.address.name}</p>
                <p className="text-sm tabular-nums text-ink-2">{prettyPhone(order.address.phone)}</p>
                <p className="text-sm text-ink-2">
                  {order.address.line}, {order.address.area}, {order.address.district}, {order.address.division}
                </p>
              </div>
            </div>
            <div className="mt-3 flex items-start gap-3 pt-3" style={{ boxShadow: "inset 0 1px 0 var(--line)" }}>
              <span className="chip-icon shrink-0">
                <Icon name="wallet" size={18} />
              </span>
              <div>
                <h2 className="text-sm font-bold">Payment</h2>
                <p className="text-sm text-ink-2">{order.payment}</p>
              </div>
            </div>
          </section>

          <section className="card rise p-4" aria-labelledby="tot-h" style={{ ["--i" as string]: 3 }}>
            <h2 id="tot-h" className="mb-2 text-sm font-bold">
              Order total
            </h2>
            <dl className="space-y-1.5 text-sm">
              <Line k="Items" v={formatBdt(order.subtotal)} />
              <Line k="Skydrop delivery" v={order.shipping ? formatBdt(order.shipping) : "Free"} />
              {order.discount > 0 && <Line k="Discount" v={"-" + formatBdt(order.discount)} good />}
              <div className="flex items-center justify-between pt-2 text-base font-extrabold" style={{ boxShadow: "inset 0 1px 0 var(--line)" }}>
                <dt>Total</dt>
                <dd className="tabular-nums">{formatBdt(order.total)}</dd>
              </div>
            </dl>
            <p className="mt-2 text-[11px] text-ink-3">Includes import duties and taxes. No extra charges at delivery.</p>
          </section>

          <section className="card rise overflow-hidden" style={{ ["--i" as string]: 4 }}>
            {cancellable && (
              <button type="button" className="ac-row w-full text-left" onClick={() => setConfirm(true)}>
                <span className="chip-icon" style={{ background: "color-mix(in srgb, var(--bad) 12%, transparent)", color: "var(--bad)" }}>
                  <Icon name="x" size={18} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold text-bad">Cancel order</span>
                  <span className="block text-xs text-ink-2">Free until we buy your items in India</span>
                </span>
                <Icon name="right" size={18} className="ac-chev" />
              </button>
            )}
            {!cancellable && s !== "cancelled" && s !== "delivered" && (
              <div className="ac-row" aria-disabled="true">
                <span className="chip-icon">
                  <Icon name="info" size={18} />
                </span>
                <span className="min-w-0 flex-1 text-xs text-ink-2">
                  Already bought in India, so this order can no longer be cancelled. Returns are covered after delivery.
                </span>
              </div>
            )}
            <Link href="/how-it-works#returns" className="ac-row">
              <span className="chip-icon">
                <Icon name="help" size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block font-semibold">Need help?</span>
                <span className="block text-xs text-ink-2">Delivery, returns and refunds</span>
              </span>
              <Icon name="right" size={18} className="ac-chev" />
            </Link>
          </section>
        </aside>
      </div>

      {confirm && (
        <ConfirmDialog
          title="Cancel this order?"
          body="We have not bought your items yet, so there is no charge. Any payment goes back to the original method."
          confirmLabel="Cancel order"
          icon="x"
          danger
          onClose={() => setConfirm(false)}
          onConfirm={() => {
            // Re-check at confirm time: the demo status may have moved past "verified".
            if (!canCancel(currentStatus(order, Date.now()))) {
              toast({ kind: "error", title: "Too late to cancel", body: "Your items were just bought in India." });
              return;
            }
            cancelOrder(order.id);
            toast({ kind: "info", title: "Order cancelled", body: `${shortId(order.id)} will not be bought.` });
          }}
        />
      )}
    </div>
  );
}

function Line({ k, v, good }: { k: string; v: string; good?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-ink-2">{k}</dt>
      <dd className={"tabular-nums font-semibold " + (good ? "text-good" : "")}>{v}</dd>
    </div>
  );
}

function Timeline({ order, status }: { order: Order; status: OrderStatus }) {
  const cur = stageIndex(status);
  return (
    <ol className="mt-5 flex flex-col lg:flex-row" aria-label="Tracking timeline">
      {STAGES.map((st, i) => {
        const state = i < cur || status === "delivered" ? "done" : i === cur ? "now" : "next";
        const last = i === STAGES.length - 1;
        return (
          <li
            key={st.status}
            className="relative flex gap-3 pb-5 last:pb-0 lg:flex-1 lg:flex-col lg:items-center lg:gap-2 lg:pb-0 lg:text-center"
            aria-current={state === "now" ? "step" : undefined}
          >
            {!last && (
              <span className="ac-seg" data-on={i < cur} style={{ ["--i" as string]: i }} aria-hidden>
                <span />
              </span>
            )}
            <span className={"ac-node " + (state === "now" && status !== "delivered" ? "pulse-dot" : "")} data-state={state} aria-hidden>
              {state === "done" ? (
                <svg width="18" height="18" viewBox="0 0 24 24" className="draw-check">
                  <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              ) : (
                <Icon name={st.icon} size={18} />
              )}
            </span>
            <div className="min-w-0 pt-1 lg:px-1 lg:pt-0">
              <p className={"text-sm font-semibold lg:text-xs " + (state === "next" ? "text-ink-3" : "text-ink")}>
                {stageLabel(st, order)}
                <span className="sr-only">{state === "done" ? " (done)" : state === "now" ? " (current)" : " (upcoming)"}</span>
              </p>
              {state === "now" ? (
                <p className="text-xs text-ink-2 lg:hidden">{st.detail}</p>
              ) : null}
              {state === "now" && (
                <span className="ac-chip mt-1 h-5 text-[10px] lg:mt-1.5">Now</span>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

function TrackingCode({ code }: { code: string }) {
  const [copied, setCopied] = useState<"idle" | "done" | "error">("idle");
  async function copy() {
    try {
      await navigator.clipboard.writeText(code);
      setCopied("done");
    } catch {
      setCopied("error");
    }
    setTimeout(() => setCopied("idle"), 1800);
  }
  return (
    <div className="mt-5 flex flex-wrap items-center gap-3 rounded-2xl bg-surface-2 p-3">
      <span className="chip-icon shrink-0">
        <Icon name="truck" size={18} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-xs text-ink-2">Skydrop tracking code</p>
        <p className="truncate font-mono text-sm font-bold tracking-wider">{code}</p>
      </div>
      <div className="flex gap-2">
        <button type="button" onClick={copy} className="btn btn-ghost h-11 bg-surface px-4 text-sm" aria-label={`Copy tracking code ${code}`}>
          <Icon name={copied === "done" ? "check" : copied === "error" ? "x" : "receipt"} size={16} />
          <span aria-live="polite">{copied === "done" ? "Copied" : copied === "error" ? "Copy failed" : "Copy"}</span>
        </button>
        <a
          href={TRACK_URL}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-primary h-11 px-4 text-sm"
        >
          Track <Icon name="arrow" size={16} className="arrow" />
          <span className="sr-only"> on track.skydrop.online, paste the code there (opens in a new tab)</span>
        </a>
      </div>
    </div>
  );
}
