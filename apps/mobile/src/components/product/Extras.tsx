// "Earn N Skydrop Points" chip and price-drop / back-in-stock alert (ported from
// apps/web/src/components/product/ProductExtras.tsx), plus the "fetching live details" shimmer.
import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import { LinearGradient } from "expo-linear-gradient";
import { router } from "expo-router";
import { api, isDemo } from "@/api/client";
import { useStore } from "@/state/store";
import { pointsToEarn } from "@/lib/points";
import type { MarketId } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "../Icon";
import { T, Tap } from "../ui";
import { useToast } from "../Toast";
import { useT } from "@/i18n";

export function ProductExtras({ id, market, priceBdt, inStock }: { id: string; market: MarketId; priceBdt: number; inStock: boolean }) {
  const { t, bdt } = useT();
  const { c } = useTheme();
  const a = useAccent(market);
  const toast = useToast();
  const { user, watches, setWatching } = useStore();
  const watching = watches.includes(id);
  const [busy, setBusy] = useState(false);
  const earn = pointsToEarn(priceBdt);

  async function toggle() {
    if (!isDemo() && !user) {
      router.push({ pathname: "/login", params: { next: `/product/${id}` } });
      return;
    }
    const type = inStock ? "price_drop" : "back_in_stock";
    setBusy(true);
    try {
      if (!watching) await api.watch(id, type, inStock ? priceBdt - 1 : undefined);
      setWatching(id, !watching);
      haptic.success();
      toast({
        kind: watching ? "info" : "success",
        title: watching ? t("px.alertRemoved") : inStock ? t("px.tellDrop") : t("px.tellBack"),
        body: watching ? undefined : t("px.sms"),
      });
    } catch {
      haptic.error();
      toast({ kind: "error", title: t("px.alertFailed"), body: t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
      {earn > 0 && (
        <View style={[styles.chip, { backgroundColor: a.soft }]} accessibilityLabel={t("px.earn", { count: earn })}>
          <Icon name="star" size={14} color={a.ink} />
          <T v="tiny" style={{ color: a.ink, fontWeight: "800" }}>
            {t("px.earn", { count: earn })}
          </T>
        </View>
      )}
      <Tap
        onPress={toggle}
        disabled={busy}
        accessibilityRole="switch"
        accessibilityState={{ checked: watching, busy }}
        accessibilityLabel={inStock ? t("px.alertDrop") : t("px.tellMe")}
        style={[styles.chip, { backgroundColor: watching ? a.accent : c.surface2, minHeight: 44 }]}
      >
        <Icon name={watching ? "check" : "bell"} size={14} color={watching ? "#fff" : c.ink2} />
        <T v="tiny" style={{ color: watching ? "#fff" : c.ink2, fontWeight: "800" }}>
          {watching ? (inStock ? t("px.watching", { price: bdt(priceBdt) }) : t("px.backOn")) : inStock ? t("px.alertDrop") : t("px.tellMe")}
        </T>
      </Tap>
    </View>
  );
}

/** Shimmer bar shown while the API refreshes a listing from the Indian store. */
export function FetchingLive({ market }: { market: MarketId }) {
  const { t } = useT();
  const a = useAccent(market);
  const [w, setW] = useState(0);
  const x = useSharedValue(0);
  useEffect(() => {
    x.value = withRepeat(withTiming(1, { duration: 1200, easing: Easing.inOut(Easing.quad) }), -1, false);
  }, [x]);
  const sweep = useAnimatedStyle(() => ({ transform: [{ translateX: -120 + x.value * (w + 120) }] }));
  return (
    <View onLayout={(e) => setW(e.nativeEvent.layout.width)} style={[styles.live, { backgroundColor: a.soft }]} accessibilityLiveRegion="polite">
      <Animated.View style={[StyleSheet.absoluteFill, { width: 120 }, sweep]} pointerEvents="none">
        <LinearGradient colors={["rgba(255,255,255,0)", "rgba(255,255,255,0.45)", "rgba(255,255,255,0)"]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={{ flex: 1 }} />
      </Animated.View>
      <Icon name="refresh-cw" size={14} color={a.ink} />
      <T v="tiny" style={{ color: a.ink, fontWeight: "800" }}>
        {t("m.fetchingFrom", { source: STORES[market].source })}
      </T>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: { flexDirection: "row", alignItems: "center", gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 999 },
  live: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, minHeight: 36, borderRadius: 12, overflow: "hidden" },
});
