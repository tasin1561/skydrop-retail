"use client";

import { useState } from "react";
import Link from "next/link";
import type { Order, OrderStatus } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { currentStatus, etaRange, isInProgress, storesOf } from "@/lib/orderStatus";
import { EmptyState, LoadingBlock, PageHead, SegTabs, useNow } from "./ui";
import { ProgressBar, StatusChip, Thumb, orderDate, shortId } from "./OrderBits";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import type { MsgKey } from "@/i18n/core";
import { StoreName } from "@/components/store/StoreName";

type Tab = "all" | "active" | "delivered" | "cancelled";

const MATCH: Record<Tab, (s: OrderStatus) => boolean> = {
  all: () => true,
  active: isInProgress,
  delivered: (s) => s === "delivered",
  cancelled: (s) => s === "cancelled",
};

const EMPTY: Record<Tab, { title: MsgKey; body: MsgKey }> = {
  all: { title: "ol.e.all", body: "ol.e.allB" },
  active: { title: "ol.e.active", body: "ol.e.activeB" },
  delivered: { title: "ol.e.delivered", body: "ol.e.deliveredB" },
  cancelled: { title: "ol.e.cancelled", body: "ol.e.cancelledB" },
};

export function OrdersList({ initialTab }: { initialTab: Tab }) {
  const { ready, user, orders } = useStore();
  const { t } = useT();
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
      <PageHead title={t("ol.title")} sub={t("ol.sub")} icon="receipt" />

      {ready && !user && (
        <div className="card rise mb-4 flex items-center gap-3 p-3 pl-4">
          <span className="chip-icon shrink-0">
            <Icon name="user" size={18} />
          </span>
          <p className="min-w-0 flex-1 text-sm text-ink-2">
            {rich(t("ol.signIn"), { signIn: <span className="font-semibold text-ink">{t("nav.signIn")}</span> })}
{orders.length > 0 && t("ol.thisDevice")}
          </p>
          <Link href="/login?next=/orders" className="btn btn-primary h-11 shrink-0 px-4 text-sm">
            {t("nav.signIn")}
          </Link>
        </div>
      )}

      <SegTabs<Tab>
        label={t("ol.filter")}
        dense
        controls="orders-panel"
        value={tab}
        onChange={change}
        items={[
          { id: "all", label: t("ol.all"), icon: "grid", count: count("all") },
          { id: "active", label: t("ol.active"), icon: "truck", count: count("active") },
          { id: "delivered", label: t("ol.done"), icon: "check", count: count("delivered") },
          { id: "cancelled", label: t("ol.cancelled"), icon: "x", count: count("cancelled") },
        ]}
      />

      <div id="orders-panel" role="tabpanel" aria-labelledby={`tab-${tab}`} className="mt-4">
        <p className="sr-only" aria-live="polite">
          {ready ? t("ol.count", { count: shown.length }) : ""}
        </p>
        {!ready ? (
          <LoadingBlock rows={3} />
        ) : shown.length === 0 ? (
          <EmptyState title={t(EMPTY[tab].title)} body={t(EMPTY[tab].body)} icon="receipt" action={orders.length ? { label: t("ol.seeAll"), onClick: () => change("all") } : { label: t("hero.start"), href: "/" }} />
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
  const tr = useT();
  const { t, bdt, digits, lang } = tr;
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
            {orderDate(o.createdAt, lang)} · {t("cart.items", { count: items }).replace(digits(items), digits(String(items).padStart(2, "0")))}
          </p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {stores.map((m) => (
              <StoreName key={m} mode={m} variant="pill" size="text-[11px]" source className="!min-h-6" />
            ))}
          </div>
        </div>
      </div>
      <div className="mt-3 flex items-center justify-between gap-2">
        <StatusChip status={s} />
        <span className="text-lg font-extrabold tabular-nums tracking-tight">{bdt(o.total)}</span>
      </div>
      <div className="mt-2.5">
        <ProgressBar status={s} label={t("ol.progressFor", { id: shortId(o.id) })} />
        {isInProgress(s) && (
          <p className="mt-1.5 flex items-center gap-1 text-[11px] text-ink-2">
            <Icon name="clock" size={12} /> {t("ol.expected", { range: etaRange(o, tr) })}
          </p>
        )}
      </div>
    </Link>
  );
}
