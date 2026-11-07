import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import Animated, { Easing, useAnimatedStyle, useReducedMotion, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import type { Order, OrderStatus } from "@/lib/types";
import { STAGES, progressOf, stageDetail, stageIndex, stageLabel, statusIcon, statusText } from "@/lib/orderStatus";
import { useTheme, withAlpha } from "@/theme";
import { Icon } from "./Icon";
import { Pill, T } from "./ui";
import { useT } from "@/i18n";
import { listEnter } from "@/lib/motion";

/** Re-renders every 15 s so simulated demo progress shows up without a refresh. */
export function useNow(ms = 15000) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), ms);
    return () => clearInterval(id);
  }, [ms]);
  return now;
}

export function StatusPill({ status }: { status: OrderStatus }) {
  const { c } = useTheme();
  const tr = useT();
  const color = status === "cancelled" ? c.bad : status === "delivered" ? c.good : c.brand;
  return <Pill text={statusText(status, tr)} icon={statusIcon(status)} color={color} bg={withAlpha(color, 0.12)} />;
}

/** Thin progress line with a travelling parcel dot. */
export function TrackBar({ status }: { status: OrderStatus }) {
  const { c } = useTheme();
  const [w, setW] = useState(0);
  const p = useSharedValue(0);
  useEffect(() => {
    p.value = withTiming(progressOf(status), { duration: 900, easing: Easing.out(Easing.cubic) });
  }, [status, p]);
  const fill = useAnimatedStyle(() => ({ transform: [{ translateX: -w + p.value * w }] }));
  const dot = useAnimatedStyle(() => ({ transform: [{ translateX: p.value * (w - 22) }] }));
  return (
    <View onLayout={(e) => setW(e.nativeEvent.layout.width)} style={{ height: 22, justifyContent: "center" }}>
      <View style={{ height: 4, borderRadius: 2, backgroundColor: c.surface2, overflow: "hidden" }}>
        <Animated.View style={[{ height: 4, width: "100%", backgroundColor: status === "cancelled" ? c.bad : c.brand }, fill]} />
      </View>
      {status !== "cancelled" && (
        <Animated.View style={[styles.parcel, { backgroundColor: c.brand, borderColor: c.surface }, dot]}>
          <Icon name="package" size={11} color="#fff" />
        </Animated.View>
      )}
    </View>
  );
}

export function Timeline({ order }: { order: Order }) {
  const { c } = useTheme();
  const tr = useT();
  const { t } = tr;
  const cur = stageIndex(order.status);
  const at = new Map((order.timeline ?? []).map((t) => [t.status, t.at]));
  const pulse = useSharedValue(1);
  const reduce = useReducedMotion();
  // current step: one slow, low-amplitude status pulse; none with Reduce Motion
  useEffect(() => {
    if (!reduce) pulse.value = withRepeat(withTiming(1.35, { duration: 2400, easing: Easing.out(Easing.quad) }), -1, false);
  }, [pulse, reduce]);
  const ring = useAnimatedStyle(() => ({ transform: [{ scale: pulse.value }], opacity: reduce ? 0 : (1.35 - pulse.value) * 1.2 }));

  if (order.status === "cancelled")
    return (
      <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
        <Icon name="x-circle" size={20} color={c.bad} />
        <T v="body" tone="bad" style={{ fontWeight: "700" }}>{t("m.cancelledBefore")}</T>
      </View>
    );

  return (
    <View accessibilityRole="list">
      {STAGES.map((s, i) => {
        const done = i < cur || order.status === "delivered";
        const active = i === cur && order.status !== "delivered";
        const future = i > cur;
        const time = at.get(s.status);
        return (
          <Animated.View key={s.status} entering={listEnter(i)} style={{ flexDirection: "row", gap: 12 }} accessibilityLabel={done ? t("m.stageDone", { label: stageLabel(s, order, tr) }) : active ? t("m.stageCurrent", { label: stageLabel(s, order, tr) }) : stageLabel(s, order, tr)}>
            <View style={{ alignItems: "center", width: 30 }}>
              <View>
                {active && <Animated.View style={[styles.ring, { backgroundColor: withAlpha(c.brand, 0.35) }, ring]} />}
                <View style={[styles.node, { backgroundColor: future ? c.surface2 : c.brand }]}>
                  <Icon name={done ? "check" : s.icon} size={14} color={future ? c.ink3 : "#fff"} />
                </View>
              </View>
              {i < STAGES.length - 1 && <View style={{ width: 2, flex: 1, minHeight: 18, backgroundColor: i < cur ? c.brand : c.line }} />}
            </View>
            <View style={{ flex: 1, paddingBottom: 16 }}>
              <T v="h3" style={{ fontSize: 14, color: future ? c.ink3 : c.ink }}>
                {stageLabel(s, order, tr)}
              </T>
              <T v="tiny" tone={future ? "ink3" : "ink2"}>
                {stageDetail(s, tr)}
              </T>
              {time && !future && (
                <T v="tiny" tone="ink3" style={{ marginTop: 2 }}>
                  {tr.dateTime(time)}
                </T>
              )}
            </View>
          </Animated.View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  parcel: { position: "absolute", left: 0, width: 22, height: 22, borderRadius: 11, alignItems: "center", justifyContent: "center", borderWidth: 2 },
  node: { width: 30, height: 30, borderRadius: 15, alignItems: "center", justifyContent: "center" },
  ring: { position: "absolute", width: 30, height: 30, borderRadius: 15 },
});
