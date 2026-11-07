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
import { formatBdt } from "@/lib/pricing";
import type { MarketId } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "../Icon";
import { T, Tap } from "../ui";
import { useToast } from "../Toast";

export function ProductExtras({ id, market, priceBdt, inStock }: { id: string; market: MarketId; priceBdt: number; inStock: boolean }) {
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
        title: watching ? "Alert removed" : inStock ? "We'll tell you when the price drops" : "We'll tell you when it's back",
        body: watching ? undefined : "You'll get an SMS as soon as it happens.",
      });
    } catch {
      haptic.error();
      toast({ kind: "error", title: "Couldn't set the alert", body: "Please try again." });
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
      {earn > 0 && (
        <View style={[styles.chip, { backgroundColor: a.soft }]} accessibilityLabel={`Earn ${earn} Skydrop Points`}>
          <Icon name="star" size={14} color={a.ink} />
          <T v="tiny" style={{ color: a.ink, fontWeight: "800" }}>
            Earn {earn} Skydrop Points
          </T>
        </View>
      )}
      <Tap
        onPress={toggle}
        disabled={busy}
        accessibilityRole="switch"
        accessibilityState={{ checked: watching, busy }}
        accessibilityLabel={inStock ? "Alert me if the price drops" : "Tell me when it's back"}
        style={[styles.chip, { backgroundColor: watching ? a.accent : c.surface2, minHeight: 44 }]}
      >
        <Icon name={watching ? "check" : "bell"} size={14} color={watching ? "#fff" : c.ink2} />
        <T v="tiny" style={{ color: watching ? "#fff" : c.ink2, fontWeight: "800" }}>
          {watching ? (inStock ? `Watching price (${formatBdt(priceBdt)})` : "Back-in-stock alert on") : inStock ? "Alert me if the price drops" : "Tell me when it's back"}
        </T>
      </Tap>
    </View>
  );
}

/** Shimmer bar shown while the API refreshes a listing from the Indian store. */
export function FetchingLive({ market }: { market: MarketId }) {
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
        Fetching live details from {STORES[market].source}
      </T>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: { flexDirection: "row", alignItems: "center", gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 999 },
  live: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, minHeight: 36, borderRadius: 12, overflow: "hidden" },
});
