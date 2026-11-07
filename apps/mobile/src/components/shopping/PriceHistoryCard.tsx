import { useEffect, useMemo, useState } from "react";
import { StyleSheet, View, type LayoutChangeEvent } from "react-native";
import Animated, { Easing, useAnimatedStyle, useReducedMotion, useSharedValue, withSpring, withTiming } from "react-native-reanimated";
import Svg, { Defs, LinearGradient, Line, Path, Stop } from "react-native-svg";
import { Card, Skeleton, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/lib/features";
import { formatBdt } from "@/lib/pricing";
import { PERIODS, shopping, type PriceHistory } from "@/lib/shopping";
import type { Product } from "@/lib/types";
import { useAccent, useTheme, withAlpha } from "@/theme";

const H = 150;
const LABEL: Record<number, string> = { 30: "30D", 90: "90D", 365: "1Y" };

const fmt = (iso: string) => new Date(iso + "T00:00:00Z").toLocaleDateString("en-GB", { day: "numeric", month: "short", timeZone: "UTC" });

/** Price history (react-native-svg): period tabs with a sliding indicator, touch crosshair, wipe-in reveal. */
export function PriceHistoryCard({ p }: { p: Product }) {
  const on = useFeature("price_history");
  const { c } = useTheme();
  const a = useAccent(p.market);
  const reduce = useReducedMotion();
  const [days, setDays] = useState<number>(30);
  const [cache, setCache] = useState<Record<number, PriceHistory>>({});
  const [failed, setFailed] = useState(false);
  const [w, setW] = useState(0);
  const [tabW, setTabW] = useState(0);
  const [hover, setHover] = useState<number | null>(null);
  const ind = useSharedValue(0);
  const wipe = useSharedValue(0);
  const data = cache[days];

  useEffect(() => {
    if (!on || cache[days]) return;
    let live = true;
    shopping
      .priceHistory(p, days)
      .then((h) => live && setCache((x) => ({ ...x, [days]: h })))
      .catch(() => live && setFailed(true));
    return () => {
      live = false;
    };
  }, [on, days, p, cache]);

  useEffect(() => {
    ind.set(withSpring(PERIODS.indexOf(days as (typeof PERIODS)[number]) * tabW, { damping: 18, stiffness: 220 }));
  }, [days, tabW, ind]);

  useEffect(() => {
    if (!data || !w) return;
    wipe.set(0);
    wipe.set(reduce ? 1 : withTiming(1, { duration: 900, easing: Easing.out(Easing.cubic) }));
  }, [data, w, reduce, wipe]);

  const indStyle = useAnimatedStyle(() => ({ transform: [{ translateX: ind.get() }] }));
  const wipeStyle = useAnimatedStyle(() => ({ transform: [{ translateX: wipe.get() * (w + 8) }] }));

  const geo = useMemo(() => {
    if (!data || !w || !data.points.length) return null;
    const vals = data.points.map((x) => x.bdt);
    const lo = Math.min(...vals);
    const hi = Math.max(...vals);
    const pad = Math.max(10, (hi - lo) * 0.12);
    const min = lo - pad;
    const max = hi + pad;
    const n = vals.length;
    const x = (i: number) => (n === 1 ? w : (i / (n - 1)) * w);
    const y = (v: number) => 10 + (1 - (v - min) / (max - min)) * (H - 18);
    let line = `M0 ${y(vals[0]).toFixed(1)}`;
    for (let i = 1; i < n; i++) line += `H${x(i).toFixed(1)}V${y(vals[i]).toFixed(1)}`;
    if (n === 1) line += `H${w}`;
    return { x, y, n, line, area: `${line}V${H}H0Z`, avg: y(data.averageBdt) };
  }, [data, w]);

  if (!on) return null;
  const idx = hover ?? (geo ? geo.n - 1 : 0);
  const pt = data?.points[idx];

  const touch = (lx: number) => {
    if (geo) setHover(Math.round(Math.min(1, Math.max(0, lx / w)) * (geo.n - 1)));
  };

  return (
    <Card>
      <View style={styles.head}>
        <T v="h3">Price history</T>
        <View style={[styles.tabs, { backgroundColor: c.surface2 }]} onLayout={(e: LayoutChangeEvent) => setTabW((e.nativeEvent.layout.width - 8) / PERIODS.length)}>
          <Animated.View style={[styles.ind, { width: tabW, backgroundColor: c.surface }, indStyle]} />
          {PERIODS.map((d) => (
            <Tap
              key={d}
              accessibilityRole="tab"
              accessibilityState={{ selected: d === days }}
              onPress={() => {
                setHover(null);
                setFailed(false);
                setDays(d);
              }}
              style={styles.tab}
            >
              <T v="small" style={{ fontWeight: "800", color: d === days ? c.ink : c.ink2 }}>
                {LABEL[d]}
              </T>
            </Tap>
          ))}
        </View>
      </View>

      {data && data.badges.length > 0 && (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 10 }}>
          {data.badges.map((b) => (
            <View key={b.type} style={[styles.badge, { backgroundColor: withAlpha(b.type === "lowest" ? c.good : a.accent, 0.12) }]}>
              <Icon name={b.type === "lowest" ? "check-circle" : "tag"} size={13} color={b.type === "lowest" ? c.good : a.ink} />
              <T v="small" style={{ fontWeight: "800", color: b.type === "lowest" ? c.good : a.ink }}>
                {b.label}
              </T>
            </View>
          ))}
        </View>
      )}

      <View style={{ marginTop: 10 }} accessibilityLiveRegion="polite">
        <T v="h2" style={{ fontVariant: ["tabular-nums"] }}>
          {pt ? formatBdt(pt.bdt) : "…"}
        </T>
        <T v="small" tone="ink2">
          {pt ? (hover === null ? "Today" : `${fmt(pt.date)} · ₹${Math.round(pt.inr).toLocaleString("en-IN")} on the store`) : "Loading"}
        </T>
      </View>

      <View
        style={{ height: H, marginTop: 8 }}
        onLayout={(e) => setW(e.nativeEvent.layout.width)}
        onStartShouldSetResponder={() => true}
        onMoveShouldSetResponder={(e) => Math.abs(e.nativeEvent.locationX) > 0}
        onResponderGrant={(e) => touch(e.nativeEvent.locationX)}
        onResponderMove={(e) => touch(e.nativeEvent.locationX)}
        onResponderRelease={() => setHover(null)}
        onResponderTerminationRequest={() => true}
        accessible
        accessibilityRole="image"
        accessibilityLabel={data ? `Price over ${days} days: lowest ${formatBdt(data.lowestBdt)}, average ${formatBdt(data.averageBdt)}, highest ${formatBdt(data.highestBdt)}.` : "Price history loading"}
      >
        {geo && data ? (
          <View style={{ overflow: "hidden", flex: 1 }}>
            <Svg width={w} height={H}>
              <Defs>
                <LinearGradient id="ph" x1="0" y1="0" x2="0" y2="1">
                  <Stop offset="0" stopColor={a.accent} stopOpacity={0.28} />
                  <Stop offset="1" stopColor={a.accent} stopOpacity={0} />
                </LinearGradient>
              </Defs>
              <Line x1={0} x2={w} y1={geo.avg} y2={geo.avg} stroke={c.ink3} strokeDasharray="4 5" strokeWidth={1} />
              <Path d={geo.area} fill="url(#ph)" />
              <Path d={geo.line} fill="none" stroke={a.accent} strokeWidth={2.25} strokeLinejoin="round" />
              {pt && hover !== null && <Line x1={geo.x(idx)} x2={geo.x(idx)} y1={0} y2={H} stroke={withAlpha(c.ink, 0.3)} strokeWidth={1} />}
            </Svg>
            {pt && <View pointerEvents="none" style={[styles.dot, { backgroundColor: a.accent, borderColor: c.surface, transform: [{ translateX: geo.x(idx) - 6 }, { translateY: geo.y(pt.bdt) - 6 }] }]} />}
            <Animated.View pointerEvents="none" style={[StyleSheet.absoluteFill, { backgroundColor: c.surface }, wipeStyle]} />
          </View>
        ) : failed ? (
          <View style={[styles.center, { backgroundColor: c.surface2 }]}>
            <T v="small" tone="ink2">
              Price history isn&apos;t available right now.
            </T>
          </View>
        ) : (
          <Skeleton h={H} />
        )}
      </View>

      {data && (
        <View style={[styles.stats, { backgroundColor: c.surface2 }]}>
          {[
            ["Lowest", data.lowestBdt, c.good],
            ["Average", data.averageBdt, c.ink],
            ["Highest", data.highestBdt, c.ink],
          ].map(([k, v, col]) => (
            <View key={k as string} style={{ flex: 1, alignItems: "center" }}>
              <T v="small" tone="ink2" style={{ fontWeight: "700" }}>
                {k as string}
              </T>
              <T v="body" style={{ fontWeight: "800", color: col as string }}>
                {formatBdt(v as number)}
              </T>
            </View>
          ))}
        </View>
      )}
      <T v="small" tone="ink3" style={{ marginTop: 8 }}>
        Past store prices at today&apos;s exchange rate.{data?.demo ? " Demo history for this sample listing." : ""}
      </T>
    </Card>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  tabs: { flexDirection: "row", padding: 4, borderRadius: 999, width: 180 },
  ind: { position: "absolute", top: 4, bottom: 4, left: 4, borderRadius: 999 },
  tab: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center" },
  badge: { flexDirection: "row", alignItems: "center", gap: 4, borderRadius: 999, paddingHorizontal: 10, paddingVertical: 5 },
  dot: { position: "absolute", left: 0, top: 0, width: 12, height: 12, borderRadius: 6, borderWidth: 2 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", borderRadius: 12 },
  stats: { flexDirection: "row", borderRadius: 14, padding: 10, marginTop: 12 },
});
