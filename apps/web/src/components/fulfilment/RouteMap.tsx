"use client";

import type { Tracking } from "@/lib/fulfilment";
import "./fulfilment.css";

// Stylised map of West Bengal + Bangladesh (original simplified artwork, no tiles).
// Equirectangular projection over lng 87.6–92.8, lat 20.6–26.7.
const W = 360;
const H = 400;
const LNG0 = 87.6;
const LNG1 = 92.8;
const LAT0 = 20.6;
const LAT1 = 26.7;
const px = (lng: number, lat: number): [number, number] => [((lng - LNG0) / (LNG1 - LNG0)) * W, ((LAT1 - lat) / (LAT1 - LAT0)) * H];

// Simplified Bangladesh outline (lng, lat), clockwise from the north-west tip.
const BD: [number, number][] = [
  [88.1, 26.4], [88.6, 26.45], [89.05, 26.35], [89.85, 25.95], [89.85, 25.3], [90.6, 25.2], [92.0, 25.15], [92.45, 24.9],
  [92.15, 24.4], [91.9, 24.15], [91.4, 24.1], [91.15, 23.65], [91.35, 23.1], [91.8, 23.25], [92.25, 23.7], [92.45, 22.8],
  [92.6, 21.3], [92.3, 20.75], [91.85, 21.8], [91.5, 22.4], [90.9, 22.15], [90.4, 21.85], [89.85, 21.85], [89.1, 21.7],
  [89.05, 22.1], [88.95, 22.85], [88.75, 23.4], [88.55, 23.7], [88.75, 24.25], [88.15, 24.45], [88.05, 24.75], [88.45, 25.2],
  [88.25, 25.55], [88.6, 25.9], [88.2, 26.05],
];
// Bay of Bengal below the coastline.
const SEA: [number, number][] = [
  [87.6, 20.6], [87.6, 21.65], [88.2, 21.65], [88.9, 21.7], [89.85, 21.85], [90.4, 21.85], [90.9, 22.15], [91.5, 22.4],
  [91.85, 21.8], [92.3, 20.75], [92.8, 20.6],
];
const poly = (pts: [number, number][]) => pts.map(([lng, lat]) => px(lng, lat).map((n) => n.toFixed(1)).join(",")).join(" ");

type Pt = { x: number; y: number };
/** Gentle arc for each leg: control point offset perpendicular to the chord. */
function ctrl(a: Pt, b: Pt, bend = 0.18): Pt {
  const mx = (a.x + b.x) / 2;
  const my = (a.y + b.y) / 2;
  const dx = b.x - a.x;
  const dy = b.y - a.y;
  return { x: mx - dy * bend, y: my + dx * bend };
}
function bez(a: Pt, c: Pt, b: Pt, t: number): Pt {
  const u = 1 - t;
  return { x: u * u * a.x + 2 * u * t * c.x + t * t * b.x, y: u * u * a.y + 2 * u * t * c.y + t * t * b.y };
}

export function RouteMap({ tracking, showRider = true }: { tracking: Tracking; showRider?: boolean }) {
  const pts = tracking.points.map((p) => {
    const [x, y] = px(p.lng, p.lat);
    return { ...p, x, y };
  });
  const legs = pts.slice(0, -1).map((a, i) => {
    const b = pts[i + 1];
    const c = ctrl(a, b);
    return { a, b, c, d: `M${a.x.toFixed(1)} ${a.y.toFixed(1)} Q${c.x.toFixed(1)} ${c.y.toFixed(1)} ${b.x.toFixed(1)} ${b.y.toFixed(1)}` };
  });
  const cancelled = tracking.status === "cancelled";
  // Progress → (leg, fraction) with equal leg weights, same as the API.
  const total = legs.length;
  const x = Math.max(0, Math.min(1, tracking.progress)) * total;
  const legIdx = Math.min(total - 1, Math.floor(x));
  const frac = tracking.progress >= 1 ? 1 : x - legIdx;
  const here = cancelled ? null : bez(legs[legIdx].a, legs[legIdx].c, legs[legIdx].b, frac);
  const rider = showRider && tracking.rider ? (() => { const [rx, ry] = px(tracking.rider.lng, tracking.rider.lat); return { x: rx, y: ry }; })() : null;
  // A stop lights up once the parcel has reached it (Kolkata from the moment it is at the hub).
  const reached = (i: number) => !cancelled && tracking.currentLeg !== null && tracking.progress >= i / total - 1e-6;

  const label = cancelled
    ? "Order cancelled"
    : `Route from ${pts[0].short} through ${pts[1].short} and ${pts[2].short} to ${pts[3].short}. ${Math.round(tracking.progress * 100)}% of the way.`;

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="ff-map" role="img" aria-label={label}>
      <polygon points={poly(SEA)} className="ff-water" />
      <polygon points={poly(BD)} className="ff-bd" />
      <text x={px(88.25, 25.0)[0] - 30} y={px(88.25, 25.0)[1]} className="ff-country" aria-hidden>
        INDIA
      </text>
      <text x={px(90.1, 24.55)[0]} y={px(90.1, 24.55)[1]} className="ff-country" aria-hidden>
        BANGLADESH
      </text>
      <text x={px(89.6, 21.1)[0]} y={px(89.6, 21.1)[1]} className="ff-country" aria-hidden>
        BAY OF BENGAL
      </text>

      {/* full route, dashed */}
      {legs.map((l, i) => (
        <path key={"t" + i} d={l.d} className="ff-track" />
      ))}
      {/* travelled part: whole legs done, current leg up to the marker */}
      {!cancelled &&
        legs.map((l, i) => {
          const off = i < legIdx || tracking.progress >= 1 ? 0 : i === legIdx ? 1 - frac : 1;
          if (off >= 1) return null;
          return <path key={"p" + i} d={l.d} pathLength={1} className="ff-leg" style={{ strokeDashoffset: off, ["--i" as string]: i }} />;
        })}

      {pts.map((p, i) => (
        <g key={p.id} className="ff-pop" style={{ ["--d" as string]: `${0.1 + i * 0.12}s` }}>
          <circle cx={p.x} cy={p.y} r={p.id === "customer" ? 6 : 5} className="ff-node" data-on={reached(i)} />
          <text
            x={p.x + (p.id === "kolkata" ? -8 : 9)}
            y={p.y + (p.id === "border" ? 14 : p.id === "kolkata" ? 16 : -8)}
            textAnchor={p.id === "kolkata" ? "middle" : "start"}
            className="ff-label"
          >
            {p.short}
          </text>
        </g>
      ))}

      {here && !rider && (
        <g aria-hidden>
          <circle cx={here.x} cy={here.y} r={7} className="ff-ring" />
          <circle cx={here.x} cy={here.y} r={6} className="ff-here" />
        </g>
      )}
      {rider && (
        <g aria-hidden>
          <circle cx={rider.x} cy={rider.y} r={7} className="ff-ring" style={{ stroke: "var(--brand-2)" }} />
          <circle cx={rider.x} cy={rider.y} r={6} className="ff-rider" />
        </g>
      )}
    </svg>
  );
}
