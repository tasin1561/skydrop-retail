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

const when = (d: string) => new Date(d).toLocaleString("en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });

/** Unboxing approval banner + returns entry point and case timeline on the order page. */
export function OrderCases({ order, status }: { order: FulfilmentOrder; status: OrderStatus }) {
  const unboxingOn = useFeature("unboxing");
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
            <span className="block font-bold">We opened your parcel in Kolkata. Please check the photos</span>
            <span className="block text-xs text-ink-2">Approve to ship it, or tell us if something is wrong. It ships automatically after 48 hours.</span>
          </span>
          <Icon name="right" size={18} className="shrink-0 transition-transform group-hover:translate-x-0.5" />
        </Link>
      )}

      {unboxingOn && order.unboxing && !awaiting && order.unboxingStatus && (
        <Link href={`/orders/${order.id}/unboxing`} className="card flex min-h-11 items-center gap-3 p-3 text-sm">
          <Icon name="verified" size={18} className="text-accent" />
          <span className="flex-1">
            Unboxing check:{" "}
            <strong>
              {order.unboxingStatus === "problem" ? "problem reported" : order.unboxingStatus === "auto_approved" ? "approved automatically" : "approved by you"}
            </strong>
          </span>
          <span className="text-accent">See photos</span>
        </Link>
      )}

      {canReturn && (
        <Link href={`/orders/${order.id}/return`} className="card flex min-h-14 items-center gap-3 p-4">
          <span className="chip-icon shrink-0">
            <Icon name="refresh" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">Return or replace an item</span>
            <span className="block text-xs text-ink-2">
              Within {elig?.windowDays ?? RETURN_WINDOW_DAYS} days of delivery
              {elig?.windowEndsAt ? `, until ${new Date(elig.windowEndsAt).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}` : ""}
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
  return (
    <section className="card p-4" aria-label={`Return ${c.id}`}>
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <h3 className="mr-auto text-sm font-bold">
          {c.stage === "india" ? "Unboxing case" : "Return"} #{c.id} · {c.reasonLabel}
        </h3>
        <span className="ac-chip" data-tone={c.status === "rejected" ? "bad" : undefined}>
          {RETURN_STATUS_LABELS[c.status]}
        </span>
      </div>
      <ol className="ff-tl text-sm">
        {c.timeline.map((e, i) => (
          <li key={i} data-tone={e.status === "rejected" ? "bad" : undefined} className="ff-fade" style={{ ["--d" as string]: `${i * 0.06}s` }}>
            <p className="font-semibold">{RETURN_STATUS_LABELS[e.status] ?? e.status}</p>
            {e.note && <p className="text-ink-2">{e.note}</p>}
            <p className="text-[11px] text-ink-3">{when(e.at)}</p>
          </li>
        ))}
      </ol>
      <p className="mt-3 text-xs text-ink-2">
        You asked for: {RESOLUTION_LABELS[c.resolution].label}
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
