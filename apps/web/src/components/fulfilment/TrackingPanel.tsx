"use client";

import { useCallback, useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled, fulfilmentApi, localTracking, type Tracking } from "@/lib/fulfilment";
import type { Order, OrderStatus } from "@/lib/types";
import { statusText } from "@/lib/orderStatus";
import { LiveMap } from "./LiveMap";
import { RouteMap } from "./RouteMap";
import "./fulfilment.css";
import { useT } from "@/i18n/client";



/**
 * Route visual for the order page: Kolkata → Benapole → Dhaka hub → customer district, ETA, last event,
 * optional live rider map (Leaflet, on demand) and proof of delivery. Feature "live_tracking_map".
 */
export function TrackingPanel({ order, status, now }: { order: Order; status: OrderStatus; now: number }) {
  const on = useFeature("live_tracking_map");
  const tr = useT();
  const { t: tx } = tr;
  const fmtDay = (d: string) => tr.date(d + (d.length === 10 ? "T00:00:00" : ""));
  const fmtWhen = (d: string) => tr.dateTime(d);
  const [remote, setRemote] = useState<Tracking | null>(null);
  const [live, setLive] = useState(false);

  const load = useCallback(() => {
    if (!apiEnabled) return;
    fulfilmentApi
      .tracking(order.id)
      .then(setRemote)
      .catch(() => setRemote(null)); // 404 when the feature is off or the order is not ours: fall back to the local view
  }, [order.id]);

  useEffect(() => {
    if (!on) return;
    load();
    // Refresh while the parcel is moving (every 60 s).
    if (status === "delivered" || status === "cancelled") return;
    const t = setInterval(load, 60_000);
    return () => clearInterval(t);
  }, [on, load, status]);

  if (!on || status === "cancelled") return null;
  const t = remote ?? localTracking(order, status, now);
  const before = t.currentLeg === null && t.status !== "delivered";
  const last = t.lastEvent;

  return (
    <section className="card rise mt-4 overflow-hidden" aria-labelledby="route-h" style={{ ["--i" as string]: 1 }}>
      <div className="grid gap-4 p-4 sm:p-5 md:grid-cols-[minmax(0,340px)_1fr]">
        <div>
          <RouteMap tracking={t} />
        </div>
        <div className="flex min-w-0 flex-col gap-4">
          <div>
            <h2 id="route-h" className="text-lg font-extrabold tracking-tight">
              {tx("tp.routeTo", { place: t.points[3]?.short })}
            </h2>
            <p className="text-sm text-ink-2">
              {before
                ? tx("tp.toHub")
                : t.status === "delivered"
                  ? tx("tp.complete")
                  : t.unboxingHold
                    ? tx("tp.awaiting")
                    : tx("tp.now", { status: statusText(t.status, tr) })}
            </p>
          </div>

          {t.eta && (
            <div className="flex items-center gap-3 rounded-2xl bg-surface-2 p-3 ff-fade">
              <span className="chip-icon shrink-0">
                <Icon name="clock" size={18} />
              </span>
              <div>
                <p className="text-xs text-ink-2">{tx("tp.eta")}</p>
                <p className="font-bold tabular-nums">
                  {t.eta.from === t.eta.to ? (t.eta.from === new Date(now).toISOString().slice(0, 10) ? tx("tp.today") : fmtDay(t.eta.from)) : `${fmtDay(t.eta.from)} – ${fmtDay(t.eta.to)}`}
                </p>
              </div>
            </div>
          )}

          <ol className="ff-legs" aria-label={tx("tp.legs")}>
            {t.legs.map((l) => (
              <li key={l.id} className="ff-legrow" data-state={l.state}>
                <span className="ff-legdot" aria-hidden />
                <span className="min-w-0 flex-1">{l.label}</span>
                <span className="text-[11px] font-semibold uppercase tracking-wide text-ink-3">
                  {l.state === "done" ? tx("tp.done") : l.state === "current" ? tx("tp.onWay") : ""}
                </span>
              </li>
            ))}
          </ol>

          {last && (
            <div className="text-sm ff-fade" style={{ ["--d" as string]: ".1s" }}>
              <p className="text-xs text-ink-2">{tx("tp.lastUpdate", { when: fmtWhen(last.at) })}</p>
              <p className="font-medium">{last.note ?? (last.status ? statusText(last.status as OrderStatus, tr) : last.event)}</p>
            </div>
          )}

          {t.rider && t.liveMap && !live && (
            <button type="button" className="btn btn-primary h-11 self-start px-4 text-sm" onClick={() => setLive(true)}>
              <Icon name="pin" size={16} /> {tx("tp.showMap")}
            </button>
          )}
        </div>
      </div>

      {live && t.rider && (
        <div className="px-4 pb-4 sm:px-5 sm:pb-5">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-bold">{tx("tp.rider")}</p>
            <button type="button" className="btn btn-ghost h-11 px-3 text-sm" onClick={() => setLive(false)}>
              {tx("tp.hideMap")}
            </button>
          </div>
          <LiveMap tracking={t} onUpdate={setRemote} />
        </div>
      )}

      {t.pod && (t.pod.photoUrl || t.pod.signatureUrl) && (
        <div className="border-t border-line px-4 py-4 sm:px-5" aria-labelledby="pod-h">
          <h3 id="pod-h" className="mb-2 flex items-center gap-2 text-sm font-bold">
            <Icon name="verified" size={16} /> {tx("tp.pod")}
          </h3>
          <div className="flex flex-wrap items-end gap-3">
            {t.pod.photoUrl && (
              <a href={t.pod.photoUrl} target="_blank" rel="noopener noreferrer" className="ff-thumb block w-28" aria-label={tx("tp.openPhoto")}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={t.pod.photoUrl} alt={tx("tp.photoAlt")} loading="lazy" />
              </a>
            )}
            {t.pod.signatureUrl && (
              <a href={t.pod.signatureUrl} target="_blank" rel="noopener noreferrer" className="ff-thumb block w-40 bg-white" style={{ aspectRatio: "2 / 1" }} aria-label={tx("tp.openSig")}>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={t.pod.signatureUrl} alt={tx("tp.signature")} loading="lazy" style={{ objectFit: "contain" }} />
              </a>
            )}
            <p className="text-xs text-ink-2">
              {t.pod.receivedBy ? tx("tp.receivedBy", { name: t.pod.receivedBy }) : tx("tp.handed")} · {fmtWhen(t.pod.at)}
            </p>
          </div>
        </div>
      )}
    </section>
  );
}
