"use client";

import { useEffect, useRef, useState } from "react";
import { fulfilmentApi, type Tracking } from "@/lib/fulfilment";
import "./fulfilment.css";
import { useT } from "@/i18n/client";

// Leaflet is not a dependency: it is loaded from cdnjs (pinned 1.9.4) only when the shopper taps "Show live map".
const LEAFLET = "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4";

type LatLng = [number, number];
type LMarker = { setLatLng: (p: LatLng) => void };
type LMap = { remove: () => void; fitBounds: (b: LatLng[], o?: object) => void; panTo: (p: LatLng, o?: object) => void };
type L = {
  map: (el: HTMLElement, o?: object) => LMap & { addLayer?: unknown };
  tileLayer: (url: string, o: object) => { addTo: (m: LMap) => void };
  marker: (p: LatLng, o?: object) => LMarker & { addTo: (m: LMap) => LMarker & { bindTooltip: (t: string) => void } };
  circleMarker: (p: LatLng, o?: object) => { addTo: (m: LMap) => { bindTooltip: (t: string) => void } };
  divIcon: (o: object) => unknown;
};

let loading: Promise<L> | null = null;
function loadLeaflet(): Promise<L> {
  const w = window as unknown as { L?: L };
  if (w.L) return Promise.resolve(w.L);
  loading ??= new Promise<L>((resolve, reject) => {
    const css = document.createElement("link");
    css.rel = "stylesheet";
    css.href = `${LEAFLET}/leaflet.min.css`;
    css.crossOrigin = "anonymous";
    document.head.appendChild(css);
    const s = document.createElement("script");
    s.src = `${LEAFLET}/leaflet.min.js`;
    s.async = true;
    s.crossOrigin = "anonymous";
    s.onload = () => (w.L ? resolve(w.L) : reject(new Error("Leaflet failed to load")));
    s.onerror = () => {
      loading = null;
      reject(new Error("Could not load the map. Check your connection."));
    };
    document.head.appendChild(s);
  });
  return loading;
}

/** OpenStreetMap with the rider's live position; polls GET /orders/{id}/tracking every 20 s while open. */
export function LiveMap({ tracking, onUpdate }: { tracking: Tracking; onUpdate?: (t: Tracking) => void }) {
  const tr = useT();
  const el = useRef<HTMLDivElement>(null);
  const rider = useRef<LMarker | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [at, setAt] = useState(tracking.rider?.at ?? null);
  const start = tracking.rider ?? tracking.position;
  const dest = tracking.points[tracking.points.length - 1];
  const hub = tracking.points.find((p) => p.id === "dhaka");

  useEffect(() => {
    let map: LMap | null = null;
    let dead = false;
    loadLeaflet()
      .then((L) => {
        if (dead || !el.current || !start) return;
        map = L.map(el.current, { zoomControl: true, attributionControl: true, scrollWheelZoom: false });
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 18,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        const icon = L.divIcon({ className: "ff-rider-icon", iconSize: [18, 18] });
        const m = L.marker([start.lat, start.lng], { icon, keyboard: false }).addTo(map);
        m.bindTooltip(tr.t("lm.rider"));
        rider.current = m;
        if (hub) L.circleMarker([hub.lat, hub.lng], { radius: 6, color: "#0f766e" }).addTo(map).bindTooltip(hub.label);
        L.circleMarker([dest.lat, dest.lng], { radius: 7, color: "#16a34a" }).addTo(map).bindTooltip(tr.t("lm.area", { label: dest.label }));
        map.fitBounds([[start.lat, start.lng], [dest.lat, dest.lng]], { padding: [32, 32], maxZoom: 14 });
      })
      .catch((e: Error) => !dead && setError(e.message));
    return () => {
      dead = true;
      map?.remove();
      rider.current = null;
    };
    // Map is created once per open; later positions move the marker.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const t = setInterval(async () => {
      try {
        const next = await fulfilmentApi.tracking(tracking.orderId);
        onUpdate?.(next);
        if (next.rider && rider.current) {
          rider.current.setLatLng([next.rider.lat, next.rider.lng]);
          setAt(next.rider.at);
        }
      } catch {
        /* keep the last position */
      }
    }, 20_000);
    return () => clearInterval(t);
  }, [tracking.orderId, onUpdate]);

  return (
    <div>
      <div ref={el} className="ff-live" role="region" aria-label={tr.t("lm.region")} />
      <p className="mt-1.5 text-[11px] text-ink-3" aria-live="polite">
        {error ??
          (at ? tr.t("lm.updated", { time: tr.time(at) }) : tr.t("lm.waiting"))}
      </p>
    </div>
  );
}
