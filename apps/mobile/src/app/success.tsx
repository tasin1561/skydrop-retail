import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeInDown, useAnimatedStyle, useSharedValue, withTiming, useReducedMotion } from "react-native-reanimated";
import Svg, { Path } from "react-native-svg";
import { deliveryWindow } from "@/lib/pricing";
import { addDays } from "@/lib/cart";
import { useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, T } from "@/components/ui";
import { useT } from "@/i18n";
import { payStatusText } from "@/lib/orderStatus";
import { enablePush, markPushOffered, shouldOfferPush } from "@/lib/push";
import { useToast } from "@/components/Toast";
import { DUR, EASE, timing, listEnter } from "@/lib/motion";

export default function Success() {
  const { t, bdt, lang } = useT();
  const { id, total, paid } = useLocalSearchParams<{ id: string; total?: string; paid?: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const w = deliveryWindow();
  const now = new Date().toISOString();

  // Success: a single check that fades and settles in once (no parachute, no bounce).
  const k = useSharedValue(0);
  const reduce = useReducedMotion();
  const toast = useToast();
  // Push permission is offered here (after an order), never on launch. Once per install.
  const [offerPush, setOfferPush] = useState(false);
  useEffect(() => {
    let live = true;
    const tm = setTimeout(() => {
      shouldOfferPush()
        .then((v) => live && setOfferPush(v))
        .catch(() => {});
    }, 2200);
    return () => {
      live = false;
      clearTimeout(tm);
    };
  }, []);
  useEffect(() => {
    k.value = reduce ? 1 : withTiming(1, timing(DUR.open));
    haptic.success();
  }, [k, reduce]);

  const check = useAnimatedStyle(() => ({ opacity: k.value, transform: [{ scale: 0.96 + k.value * 0.04 }] }));

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 24, paddingBottom: insets.bottom + 16, paddingHorizontal: 20 }}>
      <View style={{ alignItems: "center", height: 120, justifyContent: "flex-end" }}>
        <Animated.View style={[styles.check, { backgroundColor: c.dark ? "rgba(34,197,94,0.14)" : "#ecfdf3" }, check]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Svg width={40} height={40} viewBox="0 0 24 24">
            <Path d="M5 12l5 5 9-11" fill="none" stroke={c.good} strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round" />
          </Svg>
        </Animated.View>
      </View>

      <Animated.View entering={listEnter(0)} style={{ alignItems: "center", gap: 6, marginTop: 20 }}>
        <T v="h1" accessibilityRole="header" style={{ textAlign: "center" }}>{t("m.orderPlaced")}</T>
        <T v="body" tone="ink2" style={{ textAlign: "center" }}>{t("m.orderPlacedB")}</T>
      </Animated.View>

      <Animated.View entering={listEnter(0)} style={{ marginTop: 20 }}>
        <Card style={{ gap: 10 }}>
          {[
            [t("rw.order"), id],
            [t("sum.total"), total ? bdt(Number(total)) : "-"],
            ...(paid ? [[t("co.payment"), payStatusText(paid, t)]] : []),
            [t("tp.eta"), `${addDays(now, w.min, lang)} - ${addDays(now, w.max, lang)}`],
          ].map(([k, v]) => (
            <View key={k} style={{ flexDirection: "row", justifyContent: "space-between" }}>
              <T v="small" tone="ink2">
                {k}
              </T>
              <T v="small" style={{ fontWeight: "800" }} selectable>
                {v}
              </T>
            </View>
          ))}
        </Card>
      </Animated.View>

      {offerPush && (
        <Animated.View entering={FadeInDown.duration(DUR.open).easing(EASE)} style={{ marginTop: 14 }}>
          <Card style={{ gap: 10 }}>
            <View style={{ flexDirection: "row", gap: 10, alignItems: "flex-start" }}>
              <View style={{ flex: 1, gap: 2 }}>
                <T v="h3">{t("mx.pushAskT")}</T>
                <T v="small" tone="ink2">{t("mx.pushAskB")}</T>
              </View>
            </View>
            <View style={{ flexDirection: "row", gap: 10 }}>
              <Button
                label={t("mx.pushNo")}
                kind="ghost"
                style={{ flex: 1 }}
                onPress={() => {
                  markPushOffered();
                  setOfferPush(false);
                }}
              />
              <Button
                label={t("mx.pushYes")}
                icon="bell"
                style={{ flex: 1 }}
                onPress={async () => {
                  setOfferPush(false);
                  const s = await enablePush();
                  if (s === "granted") toast({ kind: "success", title: t("mx.pushOn") });
                }}
              />
            </View>
          </Card>
        </Animated.View>
      )}

      <View style={{ flex: 1 }} />
      <Animated.View entering={listEnter(0)} style={{ gap: 10 }}>
        <Button label={t("os.track")} icon="map-pin" onPress={() => router.replace(`/order/${id}`)} />
        <Button label={t("os.continue")} kind="ghost" onPress={() => router.dismissTo("/")} />
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  check: { width: 80, height: 80, borderRadius: 40, alignItems: "center", justifyContent: "center" },
});
