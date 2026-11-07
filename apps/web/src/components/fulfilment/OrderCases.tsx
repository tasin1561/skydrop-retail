"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import {
  RESOLUTION_LABELS,
  RETURN_STATUS_LABELS,
  RETURN_WINDOW_DAYS,
  apiEnabled,
  fulfilmentApi,
  type Eligibility,
  type FulfilmentOrder,
  type ReturnCase,
} from "@/lib/fulfilment";
import type { OrderStatus } from "@/lib/types";
import "./fulfilment.css";
import { useT } from "@/i18n/client";
import { hasKey, type MsgKey } from "@/i18n/core";


/** Unboxing approval banner + returns entry point and case timeline on the order page. */
export function OrderCases({ order, status }: { order: FulfilmentOrder; status: OrderStatus }) {
  const unboxingOn = useFeature("unboxing");
  const { t, date } = useT();
  const returnsOn = useFeature("returns");
  const [cases, setCases] = useState<ReturnCase[]>([]);
  const [elig, setElig] = useState<Eligibility | null>(null);

  useEffect(() => {
    if (!apiEnabled || !returnsOn) return;
    fulfilmentApi
      .returns(order.id)
      .then((r) => {
        setCases(r.items);
        setElig(r.eligibility);
      })
      .catch(() => undefined);
  }, [order.id, returnsOn, status]);

  // Demo mode has no server eligibility: delivered orders can start a return within the window.
  const delivered = status === "delivered";
  const canReturn = returnsOn && (elig ? elig.eligible : delivered && !apiEnabled);
  const awaiting = unboxingOn && order.unboxingStatus === "awaiting_customer_approval";

  if (!awaiting && !canReturn && !cases.length && !(unboxingOn && order.unboxing)) return null;

  return (
    <div className="mt-4 space-y-3">
      {awaiting && (
        <Link href={`/orders/${order.id}/unboxing`} className="ff-banner rise group" style={{ ["--i" as string]: 1 }}>
          <span className="chip-icon shrink-0 pulse-dot" style={{ background: "color-mix(in srgb, var(--warn) 18%, transparent)", color: "var(--warn)" }}>
            <Icon name="package" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-bold">{t("oc.opened")}</span>
            <span className="block text-xs text-ink-2">{t("oc.approve")}</span>
          </span>
          <Icon name="right" size={18} className="shrink-0 transition-transform group-hover:translate-x-0.5" />
        </Link>
      )}

      {unboxingOn && order.unboxing && !awaiting && order.unboxingStatus && (
        <Link href={`/orders/${order.id}/unboxing`} className="card flex min-h-11 items-center gap-3 p-3 text-sm">
          <Icon name="verified" size={18} className="text-accent" />
          <span className="flex-1">
            {t("oc.unboxing")}{" "}
            <strong>
              {order.unboxingStatus === "problem" ? "problem reported" : order.unboxingStatus === "auto_approved" ? "approved automatically" : "approved by you"}
            </strong>
          </span>
          <span className="text-accent">{t("oc.seePhotos")}</span>
        </Link>
      )}

      {canReturn && (
        <Link href={`/orders/${order.id}/return`} className="card flex min-h-14 items-center gap-3 p-4">
          <span className="chip-icon shrink-0">
            <Icon name="refresh" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">{t("oc.return")}</span>
            <span className="block text-xs text-ink-2">
              {t("oc.within", { count: elig?.windowDays ?? RETURN_WINDOW_DAYS })}
{elig?.windowEndsAt ? t("oc.until", { date: date(elig.windowEndsAt) }) : ""}
            </span>
          </span>
          <Icon name="right" size={18} className="ac-chev" />
        </Link>
      )}

      {cases.map((c) => (
        <ReturnTimeline key={c.id} c={c} />
      ))}
    </div>
  );
}

export function ReturnTimeline({ c }: { c: ReturnCase }) {
  const { t, dateTime } = useT();
  const when = (d: string) => dateTime(d);
  return (
    <section className="card p-4" aria-label={t("oc.returnAria", { id: c.id })}>
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <h3 className="mr-auto text-sm font-bold">
          {c.stage === "india" ? t("oc.unboxCase") : t("oc.returnWord")} #{c.id} · {hasKey("rr." + c.reason) ? t(("rr." + c.reason) as MsgKey) : c.reasonLabel}
        </h3>
        <span className="ac-chip" data-tone={c.status === "rejected" ? "bad" : undefined}>
          {RETURN_STATUS_LABELS[c.status] ? t(RETURN_STATUS_LABELS[c.status]) : c.statusLabel}
        </span>
      </div>
      <ol className="ff-tl text-sm">
        {c.timeline.map((e, i) => (
          <li key={i} data-tone={e.status === "rejected" ? "bad" : undefined} className="ff-fade" style={{ ["--d" as string]: `${i * 0.06}s` }}>
            <p className="font-semibold">{RETURN_STATUS_LABELS[e.status as keyof typeof RETURN_STATUS_LABELS] ? t(RETURN_STATUS_LABELS[e.status as keyof typeof RETURN_STATUS_LABELS]) : e.status}</p>
            {e.note && <p className="text-ink-2">{e.note}</p>}
            <p className="text-[11px] text-ink-3">{when(e.at)}</p>
          </li>
        ))}
      </ol>
      <p className="mt-3 text-xs text-ink-2">
        {t("oc.askedFor", { what: t(RESOLUTION_LABELS[c.resolution].label) })}
        {c.replacementOrderId && (
          <>
            {" · "}
            <Link href={`/orders/${c.replacementOrderId}`} className="font-semibold text-accent">
              Replacement {c.replacementOrderId}
            </Link>
          </>
        )}
      </p>
    </section>
  );
}
