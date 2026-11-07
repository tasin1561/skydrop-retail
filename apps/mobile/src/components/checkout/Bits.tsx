import { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, View } from "react-native";
import Animated, {
  Easing,
  FadeIn,
  ZoomIn,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import Svg, { Circle, Path, Rect } from "react-native-svg";
import type { CartItem } from "@/lib/types";
import { BRAND, CORAL, useTheme, withAlpha } from "@/theme";
import { Icon } from "../Icon";
import { Card, T, Tap } from "../ui";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";

// ---------- stepper with travelling indicator ----------
export const STEP_LABELS: MsgKey[] = ["co.step.address", "co.step.delivery", "co.step.payment", "co.step.review"];

export function Stepper({ step, onJump }: { step: number; onJump: (i: number) => void }) {
  const { t, digits } = useT();
  const { c } = useTheme();
  const [w, setW] = useState(0);
  const x = useSharedValue(0);
  useEffect(() => {
    x.value = withSpring(step, { damping: 18, stiffness: 200 });
  }, [step, x]);
  const seg = w / STEP_LABELS.length;
  const fill = useAnimatedStyle(() => ({ transform: [{ translateX: x.value * seg }] }));
  return (
    <View onLayout={(e) => setW(e.nativeEvent.layout.width)} style={{ gap: 8 }}>
      <View style={{ flexDirection: "row" }}>
        {STEP_LABELS.map((k, i) => {
          const l = t(k);
          const done = i < step;
          const on = i === step;
          return (
            <Tap
              key={l}
              onPress={() => done && onJump(i)}
              disabled={!done}
              accessibilityLabel={t("m.stepA11y", { n: i + 1, label: done ? t("m.stageDone", { label: l }) : on ? t("m.stageCurrent", { label: l }) : l })}
              style={{ flex: 1, alignItems: "center", gap: 4, minHeight: 48, justifyContent: "center" }}
            >
              <View style={[styles.stepDot, { backgroundColor: done || on ? BRAND : c.surface2 }]}>
                {done ? (
                  <Icon name="check" size={13} color="#fff" />
                ) : (
                  <T v="tiny" style={{ fontWeight: "800", color: on ? "#fff" : c.ink3 }}>
                    {digits(i + 1)}
                  </T>
                )}
              </View>
              <T v="tiny" style={{ fontWeight: on ? "800" : "600", color: on ? c.ink : c.ink3 }}>
                {l}
              </T>
            </Tap>
          );
        })}
      </View>
      <View style={{ height: 3, borderRadius: 2, backgroundColor: c.surface2, overflow: "hidden" }}>
        <Animated.View style={[{ width: seg, height: 3, borderRadius: 2, backgroundColor: BRAND }, fill]} />
      </View>
    </View>
  );
}

// ---------- live verification panel ----------
export type CheckState = "wait" | "run" | "ok" | "warn" | "fail";
/** Labels are dictionary keys. */
export const CHECKS: { id: string; run: MsgKey; ok: MsgKey; warn: MsgKey; fail: MsgKey }[] = [
  { id: "price", run: "vp.priceRun", ok: "vp.priceOk", warn: "vp.priceWarn", fail: "vp.priceFail" },
  { id: "stock", run: "vp.stockRun", ok: "vp.stockOk", warn: "vp.stockOk", fail: "vp.stockFail" },
  { id: "hub", run: "vp.hubRun", ok: "vp.hubOk", warn: "vp.hubOk", fail: "vp.hubFail" },
];

export function VerifyPanel({ states, changed, failed, error }: { states: CheckState[]; changed: { item: CartItem; fresh: number }[]; failed: CartItem[]; error: string | null }) {
  const { t, bdt } = useT();
  const { c } = useTheme();
  return (
    <Card style={{ gap: 10 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <Icon name="shield" size={17} color={BRAND} />
        <T v="h3" style={{ fontSize: 15 }}>{t("vp.title")}</T>
      </View>
      <View accessibilityLiveRegion="polite" style={{ gap: 8 }}>
        {CHECKS.map((ck, i) => {
          const s = states[i];
          const color = s === "fail" ? c.bad : s === "warn" ? c.warn : s === "ok" ? c.good : c.ink3;
          return (
            <View key={ck.id} style={{ flexDirection: "row", alignItems: "center", gap: 10, minHeight: 28 }}>
              <View style={[styles.checkDot, { backgroundColor: s === "wait" ? c.surface2 : withAlpha(color, 0.14) }]}>
                {s === "run" ? (
                  <ActivityIndicator size="small" color={BRAND} />
                ) : s === "wait" ? (
                  <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: c.ink3 }} />
                ) : (
                  <Animated.View entering={ZoomIn.springify()}>
                    <Icon name={s === "ok" ? "check" : s === "warn" ? "info" : "x"} size={14} color={color} />
                  </Animated.View>
                )}
              </View>
              <T v="small" style={{ flex: 1, color: s === "wait" ? c.ink3 : s === "fail" ? c.bad : c.ink, fontWeight: s === "run" ? "700" : "500" }}>
                {t(s === "wait" || s === "run" ? ck.run : s === "ok" ? ck.ok : s === "warn" ? ck.warn : ck.fail)}
              </T>
            </View>
          );
        })}
      </View>
      {error && (
        <T v="small" tone="bad">
          {error}
        </T>
      )}
      {changed.map(({ item, fresh }) => (
        <Animated.View key={item.productId + item.variant} entering={FadeIn} style={[styles.note, { backgroundColor: withAlpha(c.warn, 0.12) }]}>
          <T v="small" numberOfLines={1} style={{ fontWeight: "700" }}>
            {item.title}
          </T>
          <T v="tiny" tone="ink2">
            {t("m.priceChanged", { from: bdt(item.unitBdt), to: bdt(fresh) })}
          </T>
        </Animated.View>
      ))}
      {failed.map((item) => (
        <View key={item.productId + item.variant} style={[styles.note, { backgroundColor: withAlpha(c.bad, 0.1) }]}>
          <T v="small" numberOfLines={1} style={{ fontWeight: "700" }}>
            {item.title}
          </T>
          <T v="tiny" tone="bad">{t("m.notAvail")}</T>
        </View>
      ))}
    </Card>
  );
}

// ---------- Skydrop van driving across while the order is placed ----------
export function VanDrive({ label }: { label: string }) {
  const { c } = useTheme();
  const [w, setW] = useState(0);
  const x = useSharedValue(0);
  const bob = useSharedValue(0);
  useEffect(() => {
    x.value = withRepeat(withTiming(1, { duration: 1800, easing: Easing.inOut(Easing.quad) }), -1, false);
    bob.value = withRepeat(withSequence(withTiming(-2, { duration: 140 }), withTiming(0, { duration: 140 })), -1);
  }, [x, bob]);
  const van = useAnimatedStyle(() => ({ transform: [{ translateX: -90 + x.value * (w + 90) }, { translateY: bob.value }] }));
  const road = useAnimatedStyle(() => ({ transform: [{ translateX: -(x.value * 40) % 40 }] }));
  return (
    <View style={{ gap: 10 }} accessibilityLabel={label} accessibilityLiveRegion="polite">
      <View onLayout={(e) => setW(e.nativeEvent.layout.width)} style={{ height: 64, overflow: "hidden", justifyContent: "flex-end" }}>
        <Animated.View style={[{ flexDirection: "row", gap: 16, marginBottom: 2, width: w + 80 }, road]}>
          {Array.from({ length: Math.ceil((w + 80) / 40) }, (_, i) => (
            <View key={i} style={{ width: 24, height: 3, borderRadius: 2, backgroundColor: c.line }} />
          ))}
        </Animated.View>
        <Animated.View style={[{ position: "absolute", bottom: 6, left: 0 }, van]}>
          <Svg width={84} height={50} viewBox="0 0 84 50">
            <Rect x={2} y={8} width={50} height={30} rx={6} fill={BRAND} />
            <Path d="M52 16h14l12 12v10H52z" fill={BRAND} />
            <Path d="M56 19h9l8 8H56z" fill="#e4defe" />
            <Rect x={10} y={16} width={30} height={10} rx={3} fill={CORAL} />
            <Circle cx={18} cy={40} r={7} fill="#0b0b0f" />
            <Circle cx={18} cy={40} r={3} fill="#e7e5de" />
            <Circle cx={64} cy={40} r={7} fill="#0b0b0f" />
            <Circle cx={64} cy={40} r={3} fill="#e7e5de" />
          </Svg>
        </Animated.View>
      </View>
      <T v="small" style={{ textAlign: "center", fontWeight: "700" }}>
        {label}
      </T>
    </View>
  );
}

const styles = StyleSheet.create({
  stepDot: { width: 24, height: 24, borderRadius: 12, alignItems: "center", justifyContent: "center" },
  checkDot: { width: 28, height: 28, borderRadius: 14, alignItems: "center", justifyContent: "center" },
  note: { padding: 10, borderRadius: 12, gap: 2 },
});
