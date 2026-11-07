"use client";

import { useState } from "react";
import Link from "next/link";
import type { MarketId, Order, OrderStatus } from "@/lib/types";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { groupByMarket } from "@/lib/pricing";
import { STAGES, canCancel, currentStatus, etaRange, isInProgress, stageDetail, stageIndex, stageLabel } from "@/lib/orderStatus";
import { ConfirmDialog, EmptyState, LoadingBlock, PageHead, useNow } from "./ui";
import { ProgressBar, StatusChip, Thumb, orderDate, shortId } from "./OrderBits";
import { prettyPhone } from "./phone";
import { SafeSection } from "@/components/resilience/SafeSection";
import { TrackingPanel } from "@/components/fulfilment/TrackingPanel";
import { OrderCases } from "@/components/fulfilment/OrderCases";
import { useT } from "@/i18n/client";
import { bdName } from "@/lib/bd";
import { apiEnabled } from "@/lib/apiClient";
import { SignedOut } from "./SignedOut";

const TRACK_URL = "https://track.skydrop.online";

export function OrderDetail({ id }: { id: string }) {
  const { ready, user, orders, cancelOrder } = useStore();
  const toast = useToast();
  const tr = useT();
  const { t, bdt, digits, lang } = tr;
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

  if (!order && apiEnabled && !user)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
        <SignedOut next={`/orders/${id}`} title={t("od.signIn")} body={t("od.signInB")} />
      </div>
    );

  if (!order)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
        <PageHead title={t("od.notFound")} icon="receipt" back={{ href: "/orders", label: t("od.allOrders") }} />
        <EmptyState
          title={t("od.cantFind")}
          body={t(apiEnabled ? "od.cantFindApi" : "od.cantFindB")}
          action={{ label: t("od.seeYours"), href: "/orders" }}
        />
      </div>
    );

  const s = currentStatus(order, now);
  const cancellable = canCancel(s);

  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title={t("od.title", { id: shortId(order.id) })} sub={t("od.placed", { date: orderDate(order.createdAt, lang) })} icon="receipt" back={{ href: "/orders", label: t("od.allOrders") }} />

      {/* summary + tracking */}
      <section className="card rise p-4 sm:p-5" aria-labelledby="track-h">
        <div className="flex flex-wrap items-center gap-2">
          <h2 id="track-h" className="mr-auto text-lg font-extrabold tracking-tight">
            {t("od.tracking")}
          </h2>
          <StatusChip status={s} />
          {isInProgress(s) && (
            <span className="ac-chip" data-tone="muted">
              <Icon name="clock" size={12} /> {t("od.eta", { range: etaRange(order, tr) })}
            </span>
          )}
        </div>
        <div className="mt-3">
          <ProgressBar status={s} />
          <p className="mt-1.5 text-xs text-ink-2" aria-live="polite">
            {s === "cancelled"
              ? t("od.cancelledMsg")
              : s === "delivered"
                ? t("od.deliveredMsg")
                : t("od.stepMsg", { n: digits(String(stageIndex(s) + 1).padStart(2, "0")), total: digits(String(STAGES.length).padStart(2, "0")), label: stageLabel(STAGES[stageIndex(s)], order, tr) })}
          </p>
        </div>

        {s !== "cancelled" && <Timeline order={order} status={s} />}

        {/* tracking code */}
        <TrackingCode code={order.trackingCode} />
        <p className="mt-2 text-[11px] text-ink-3">{t("od.demo")}</p>
      </section>

      {/* fulfilment: route map + live rider, unboxing approval, returns */}
      <SafeSection label={t("od.routeMap")} quiet>
        <TrackingPanel order={order} status={s} now={now} />
      </SafeSection>
      <SafeSection label={t("od.unboxReturns")}>
        <OrderCases order={order} status={s} />
      </SafeSection>

      <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_340px]">
        {/* items by store */}
        <section aria-labelledby="items-h" className="space-y-3">
          <h2 id="items-h" className="sr-only">
            {t("od.items")}
          </h2>
          {(Object.entries(groupByMarket(order.items)) as [MarketId, Order["items"]][]).map(([m, items], gi) => (
            <div key={m} data-store={m} className="card rise overflow-hidden" style={{ ["--i" as string]: gi + 1 }}>
              <div className="flex items-center gap-3 px-4 py-3" style={{ background: "var(--accent-soft)" }}>
                <span className="chip-icon is-active">
                  <Icon name={STORES[m].icon} size={18} />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="font-bold">{STORES[m].name}</p>
                  <p className="text-xs text-ink-2">{t("od.boughtFrom", { source: STORES[m].source })}</p>
                </div>
                <span className="ac-chip tabular-nums">{t("cart.items", { count: items.reduce((n, i) => n + i.qty, 0) })}</span>
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
                        {it.variant ? it.variant + " · " : ""}{t("od.qtyLine", { qty: it.qty, price: bdt(it.unitBdt) })}
                      </p>
                    </div>
                    <span className="shrink-0 font-bold tabular-nums">{bdt(it.unitBdt * it.qty)}</span>
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
                  {t("od.address")}
                </h2>
                <p className="mt-1 text-sm font-semibold">{order.address.name}</p>
                <p className="text-sm tabular-nums text-ink-2">{prettyPhone(order.address.phone)}</p>
                <p className="text-sm text-ink-2">
                  {order.address.line}, {order.address.area}, {bdName(order.address.district, lang)}, {bdName(order.address.division, lang)}
                </p>
              </div>
            </div>
            <div className="mt-3 flex items-start gap-3 pt-3" style={{ boxShadow: "inset 0 1px 0 var(--line)" }}>
              <span className="chip-icon shrink-0">
                <Icon name="wallet" size={18} />
              </span>
              <div>
                <h2 className="text-sm font-bold">{t("od.payment")}</h2>
                <p className="text-sm text-ink-2">{order.payment}</p>
              </div>
            </div>
          </section>

          <section className="card rise p-4" aria-labelledby="tot-h" style={{ ["--i" as string]: 3 }}>
            <h2 id="tot-h" className="mb-2 text-sm font-bold">
              {t("od.total")}
            </h2>
            <dl className="space-y-1.5 text-sm">
              <Line k={t("od.items")} v={bdt(order.subtotal)} />
              <Line k={t("od.delivery")} v={order.shipping ? bdt(order.shipping) : t("common.free")} />
              {order.discount > 0 && <Line k={t("od.discount")} v={"-" + bdt(order.discount)} good />}
              <div className="flex items-center justify-between pt-2 text-base font-extrabold" style={{ boxShadow: "inset 0 1px 0 var(--line)" }}>
                <dt>{t("sum.total")}</dt>
                <dd className="tabular-nums">{bdt(order.total)}</dd>
              </div>
            </dl>
            <p className="mt-2 text-[11px] text-ink-3">{t("od.includes")}</p>
          </section>

          <section className="card rise overflow-hidden" style={{ ["--i" as string]: 4 }}>
            {cancellable && (
              <button type="button" className="ac-row w-full text-left" onClick={() => setConfirm(true)}>
                <span className="chip-icon" style={{ background: "color-mix(in srgb, var(--bad) 12%, transparent)", color: "var(--bad)" }}>
                  <Icon name="x" size={18} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold text-bad">{t("od.cancel")}</span>
                  <span className="block text-xs text-ink-2">{t("od.cancelFree")}</span>
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
                  {t("od.cantCancel")}
                </span>
              </div>
            )}
            <Link href="/how-it-works#returns" className="ac-row">
              <span className="chip-icon">
                <Icon name="help" size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block font-semibold">{t("od.needHelp")}</span>
                <span className="block text-xs text-ink-2">{t("od.needHelpSub")}</span>
              </span>
              <Icon name="right" size={18} className="ac-chev" />
            </Link>
          </section>
        </aside>
      </div>

      {confirm && (
        <ConfirmDialog
          title={t("od.cancelQ")}
          body={t("od.cancelBody")}
          confirmLabel={t("od.cancel")}
          icon="x"
          danger
          onClose={() => setConfirm(false)}
          onConfirm={() => {
            // Re-check at confirm time: the demo status may have moved past "verified".
            if (!canCancel(currentStatus(order, Date.now()))) {
              toast({ kind: "error", title: t("od.tooLate"), body: t("od.tooLateB") });
              return;
            }
            cancelOrder(order.id);
            toast({ kind: "info", title: t("od.cancelledT"), body: t("od.cancelledB", { id: shortId(order.id) }) });
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
  const tr = useT();
  const { t } = tr;
  const cur = stageIndex(status);
  return (
    <ol className="mt-5 flex flex-col lg:flex-row" aria-label={t("od.timeline")} data-reveal="fade" suppressHydrationWarning>
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
                {stageLabel(st, order, tr)}
                <span className="sr-only">{state === "done" ? t("lg.done") : state === "now" ? t("lg.current") : t("od.upcoming")}</span>
              </p>
              {state === "now" ? (
                <p className="text-xs text-ink-2 lg:hidden">{stageDetail(st, tr)}</p>
              ) : null}
              {state === "now" && (
                <span className="ac-chip mt-1 h-5 text-[10px] lg:mt-1.5">{t("od.now")}</span>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

function TrackingCode({ code }: { code: string }) {
  const tr = useT();
  const { t } = tr;
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
      <div className="min-w-[10rem] flex-1">
        <p className="text-xs text-ink-2">{t("od.code")}</p>
        <p className="break-all font-mono text-sm font-bold tracking-wider">{code}</p>
      </div>
      {/* wraps under the code on narrow phones so the code is never cut off */}
      <div className="flex grow gap-2 sm:grow-0 [&>*]:flex-1 sm:[&>*]:flex-none">
        <button type="button" onClick={copy} className="btn btn-ghost h-11 bg-surface px-4 text-sm" aria-label={t("od.copyCode", { code })}>
          <Icon name={copied === "done" ? "check" : copied === "error" ? "x" : "receipt"} size={16} />
          <span aria-live="polite">{copied === "done" ? t("common.copied") : copied === "error" ? t("common.copyFailed") : t("common.copy")}</span>
        </button>
        <a
          href={TRACK_URL}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-primary h-11 px-4 text-sm"
        >
          {t("od.track")} <Icon name="arrow" size={16} className="arrow" />
          <span className="sr-only">{t("od.trackSr")}</span>
        </a>
      </div>
    </div>
  );
}
