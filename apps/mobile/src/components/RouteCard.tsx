import { useEffect } from "react";
import { View } from "react-native";
import Svg, { Circle, Path, Polygon, Text as SvgText } from "react-native-svg";
import Animated, { Easing, useAnimatedProps, useReducedMotion, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import type { Tracking } from "@/api/fulfilment";
import { useTheme, withAlpha } from "@/theme";
import { Card, T } from "./ui";

// Same stylised map as the web (apps/web/src/components/fulfilment/RouteMap.tsx): original artwork, no tiles.
const W = 360;
const H = 400;
const px = (lng: number, lat: number) => ({ x: ((lng - 87.6) / 5.2) * W, y: ((26.7 - lat) / 6.1) * H });
const BD: [number, number][] = [
  [88.1, 26.4], [88.6, 26.45], [89.05, 26.35], [89.85, 25.95], [89.85, 25.3], [90.6, 25.2], [92.0, 25.15], [92.45, 24.9],
  [92.15, 24.4], [91.9, 24.15], [91.4, 24.1], [91.15, 23.65], [91.35, 23.1], [91.8, 23.25], [92.25, 23.7], [92.45, 22.8],
  [92.6, 21.3], [92.3, 20.75], [91.85, 21.8], [91.5, 22.4], [90.9, 22.15], [90.4, 21.85], [89.85, 21.85], [89.1, 21.7],
  [89.05, 22.1], [88.95, 22.85], [88.75, 23.4], [88.55, 23.7], [88.75, 24.25], [88.15, 24.45], [88.05, 24.75], [88.45, 25.2],
  [88.25, 25.55], [88.6, 25.9], [88.2, 26.05],
];
const SEA: [number, number][] = [
  [87.6, 20.6], [87.6, 21.65], [88.2, 21.65], [88.9, 21.7], [89.85, 21.85], [90.4, 21.85], [90.9, 22.15], [91.5, 22.4],
  [91.85, 21.8], [92.3, 20.75], [92.8, 20.6],
];
const poly = (pts: [number, number][]) => pts.map(([lng, lat]) => { const p = px(lng, lat); return `${p.x.toFixed(1)},${p.y.toFixed(1)}`; }).join(" ");

const APath = Animated.createAnimatedComponent(Path);
const ACircle = Animated.createAnimatedComponent(Circle);

type Pt = { x: number; y: number };
const ctrl = (a: Pt, b: Pt): Pt => ({ x: (a.x + b.x) / 2 - (b.y - a.y) * 0.18, y: (a.y + b.y) / 2 + (b.x - a.x) * 0.18 });
const bez = (a: Pt, c: Pt, b: Pt, t: number): Pt => {
  const u = 1 - t;
  return { x: u * u * a.x + 2 * u * t * c.x + t * t * b.x, y: u * u * a.y + 2 * u * t * c.y + t * t * b.y };
};

function Leg({ d, offset, delay, color }: { d: string; offset: number; delay: number; color: string }) {
  const reduce = useReducedMotion();
  const off = useSharedValue(reduce ? offset : 1);
  useEffect(() => {
    off.value = reduce ? offset : withTiming(offset, { duration: 1100 + delay, easing: Easing.out(Easing.cubic) });
  }, [offset, delay, reduce, off]);
  const props = useAnimatedProps(() => ({ strokeDashoffset: off.value }));
  return <APath d={d} fill="none" stroke={color} strokeWidth={4} strokeLinecap="round" strokeDasharray={[1, 1]} pathLength={1} animatedProps={props} />;
}

function Pulse({ x, y, color }: { x: number; y: number; color: string }) {
  const reduce = useReducedMotion();
  const s = useSharedValue(0);
  useEffect(() => {
    if (!reduce) s.value = withRepeat(withTiming(1, { duration: 1800, easing: Easing.out(Easing.quad) }), -1, false);
  }, [reduce, s]);
  // Radius + opacity on the ring only (the dot itself never moves).
  const props = useAnimatedProps(() => ({ r: 7 + s.value * 10, opacity: reduce ? 0 : 0.9 * (1 - s.value) }));
  return (
    <>
      <ACircle cx={x} cy={y} fill="none" stroke={color} strokeWidth={2} animatedProps={props} />
      <Circle cx={x} cy={y} r={6} fill={color} />
    </>
  );
}

const fmtDay = (d: string) => new Date(d + "T00:00:00").toLocaleDateString("en-GB", { day: "numeric", month: "short" });

/** Order screen: stylised route Kolkata → Benapole → Dhaka hub → customer district, legs, ETA and last event. */
export function RouteCard({ tracking }: { tracking: Tracking }) {
  const { c } = useTheme();
  const pts = tracking.points.map((p) => ({ ...p, ...px(p.lng, p.lat) }));
  const legs = pts.slice(0, -1).map((a, i) => {
    const b = pts[i + 1];
    const k = ctrl(a, b);
    return { a, b, k, d: `M${a.x} ${a.y} Q${k.x} ${k.y} ${b.x} ${b.y}` };
  });
  const cancelled = tracking.status === "cancelled";
  const x = Math.max(0, Math.min(1, tracking.progress)) * legs.length;
  const li = Math.min(legs.length - 1, Math.floor(x));
  const f = tracking.progress >= 1 ? 1 : x - li;
  const here = bez(legs[li].a, legs[li].k, legs[li].b, f);
  const rider = tracking.rider ? px(tracking.rider.lng, tracking.rider.lat) : null;

  return (
    <Card style={{ gap: 12 }}>
      <T v="h2" accessibilityRole="header">
        Route to {pts[3]?.short}
      </T>
      <View
        accessible
        accessibilityLabel={`Route from Kolkata through Benapole and Dhaka hub to ${pts[3]?.short}, ${Math.round(tracking.progress * 100)} percent of the way`}
        style={{ borderRadius: 16, overflow: "hidden", backgroundColor: withAlpha(c.ink, 0.04), aspectRatio: W / H, maxHeight: 360, alignSelf: "center", width: "100%" }}
      >
        <Svg viewBox={`0 0 ${W} ${H}`} width="100%" height="100%">
          <Polygon points={poly(SEA)} fill={withAlpha(c.brand, 0.1)} />
          <Polygon points={poly(BD)} fill={withAlpha(c.brand, 0.08)} stroke={withAlpha(c.brand, 0.45)} strokeWidth={1.2} strokeDasharray="3 3" />
          {legs.map((l, i) => (
            <Path key={"t" + i} d={l.d} fill="none" stroke={withAlpha(c.ink3, 0.6)} strokeWidth={2.5} strokeDasharray="2 5" strokeLinecap="round" />
          ))}
          {!cancelled &&
            legs.map((l, i) => {
              const off = i < li || tracking.progress >= 1 ? 0 : i === li ? 1 - f : 1;
              return off >= 1 ? null : <Leg key={"p" + i} d={l.d} offset={off} delay={i * 300} color={c.brand} />;
            })}
          {pts.map((p, i) => {
            const on = !cancelled && tracking.currentLeg !== null && tracking.progress >= i / legs.length - 1e-6;
            return (
              <Circle key={p.id} cx={p.x} cy={p.y} r={p.id === "customer" ? 6 : 5} fill={on ? c.brand : c.surface} stroke={on ? c.brand : c.ink3} strokeWidth={2} />
            );
          })}
          {pts.map((p) => (
            <SvgText key={"l" + p.id} x={p.x + 9} y={p.y + (p.id === "border" || p.id === "kolkata" ? 15 : -8)} fill={c.ink2} fontSize={11} fontWeight="600">
              {p.short}
            </SvgText>
          ))}
          {!cancelled && (rider ? <Pulse x={rider.x} y={rider.y} color="#ff6a3d" /> : <Pulse x={here.x} y={here.y} color={c.brand} />)}
        </Svg>
      </View>

      {tracking.eta && (
        <T v="small" style={{ fontWeight: "700" }}>
          Estimated delivery {tracking.eta.from === tracking.eta.to ? fmtDay(tracking.eta.from) : `${fmtDay(tracking.eta.from)} – ${fmtDay(tracking.eta.to)}`}
        </T>
      )}
      <View style={{ gap: 8 }}>
        {tracking.legs.map((l) => (
          <View key={l.id} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
            <View
              style={{
                width: 10, height: 10, borderRadius: 5,
                backgroundColor: l.state === "next" ? c.surface2 : c.brand,
                borderWidth: l.state === "current" ? 3 : 0, borderColor: withAlpha(c.brand, 0.3),
              }}
            />
            <T v="small" tone={l.state === "next" ? "ink3" : undefined} style={{ flex: 1 }}>
              {l.label}
            </T>
            <T v="tiny" tone="ink3">
              {l.state === "done" ? "DONE" : l.state === "current" ? "ON THE WAY" : ""}
            </T>
          </View>
        ))}
      </View>
      {tracking.lastEvent && (
        <View>
          <T v="tiny" tone="ink3">
            Last update · {new Date(tracking.lastEvent.at).toLocaleString("en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })}
          </T>
          <T v="small">{tracking.lastEvent.note ?? tracking.lastEvent.status}</T>
        </View>
      )}
      {tracking.unboxingHold && (
        <T v="small" style={{ color: c.warn, fontWeight: "700" }}>
          Waiting at the Kolkata hub for your unboxing approval. Open the order on the website to see the photos.
        </T>
      )}
      {tracking.pod?.receivedBy && (
        <T v="small" tone="ink2">
          Proof of delivery: received by {tracking.pod.receivedBy}
        </T>
      )}
    </Card>
  );
}
