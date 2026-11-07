// First launch: "Where would you like to shop?" before the home tab. Asked once (AsyncStorage), skippable
// (Skip / Android back = all stores). Only stores that are switched on are offered; with one store, never shown.
import { useEffect, useState } from "react";
import { AccessibilityInfo, Modal, ScrollView, StyleSheet, View } from "react-native";
import Animated, { runOnJS, useAnimatedStyle, useSharedValue, withTiming } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { usePathname } from "expo-router";
import type { MarketId, StoreMode } from "@/lib/types";
import { storeText } from "@/lib/markets";
import { useFeature } from "@/lib/features";
import { useStore } from "@/state/store";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { DUR, timing } from "@/lib/motion";
import { useT } from "@/i18n";
import { Icon } from "./Icon";
import { IconChip, T, Tap } from "./ui";
import { StoreName } from "./StoreName";

export const STORE_PICKED_KEY = "skydrop-retail:store-picked";

export function StorePicker() {
  const { ready, enabled, setMode } = useStore();
  const path = usePathname();
  const flags: Record<MarketId, boolean> = {
    amazon: useFeature("store.amazon"),
    flipkart: useFeature("store.flipkart"),
    bigbasket: useFeature("store.bigbasket"),
  };
  const stores = enabled.filter((m): m is MarketId => m !== "all" && flags[m]);
  const [picked, setPicked] = useState<boolean | null>(null); // null: not read yet
  const [phase, setPhase] = useState<"wait" | "open" | "done">("wait");

  useEffect(() => {
    AsyncStorage.getItem(STORE_PICKED_KEY)
      .then((v) => setPicked(v === "1"))
      .catch(() => setPicked(true)); // storage broken: don't nag on every launch
  }, []);

  // only on the home tab (a push deep link to a product is never interrupted)
  const home = path === "/" || path === "/index";
  // opens once the stored answer is known and the shopper is on Home (state adjusted during render, no effect)
  if (phase === "wait" && ready && picked === false && home && stores.length >= 2) setPhase("open");

  const choose = (m: StoreMode) => {
    setMode(m);
    setPicked(true);
    AsyncStorage.setItem(STORE_PICKED_KEY, "1").catch(() => {});
  };

  if (phase !== "open") return null;
  return <PickerScreen stores={stores} onChoose={choose} onClosed={() => setPhase("done")} />;
}

function PickerScreen({ stores, onChoose, onClosed }: { stores: MarketId[]; onChoose: (m: StoreMode) => void; onClosed: () => void }) {
  const { t } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const p = useSharedValue(0);
  const [leaving, setLeaving] = useState(false);

  useEffect(() => {
    p.set(withTiming(1, timing(DUR.open)));
  }, [p]);

  const finish = (m: StoreMode) => {
    if (leaving) return;
    setLeaving(true);
    haptic.tap();
    onChoose(m);
    AccessibilityInfo.announceForAccessibility(t("mx.nowShopping", { store: storeText(m, t).name }));
    p.set(
      withTiming(0, timing(DUR.base), (done) => {
        if (done) runOnJS(onClosed)();
      }),
    );
  };

  const fade = useAnimatedStyle(() => ({ opacity: p.value }));
  const rise = useAnimatedStyle(() => ({ opacity: p.value, transform: [{ translateY: (1 - p.value) * 16 }] }));

  return (
    <Modal visible transparent statusBarTranslucent navigationBarTranslucent animationType="none" onRequestClose={() => finish("all")}>
      <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: c.bg }, fade]} />
      <Animated.View style={[{ flex: 1 }, rise]} accessibilityViewIsModal>
        <ScrollView
          contentContainerStyle={{ paddingTop: insets.top + 12, paddingBottom: insets.bottom + 24, paddingHorizontal: 20, flexGrow: 1 }}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.top}>
            <Tap
              onPress={() => finish("all")}
              accessibilityLabel={t("mx.pick.skipA11y")}
              hitSlop={8}
              style={[styles.skip, { backgroundColor: c.surface2 }]}
            >
              <T v="small" tone="ink2" style={{ fontWeight: "700" }}>
                {t("mx.pick.skip")}
              </T>
            </Tap>
          </View>

          <T v="h1" accessibilityRole="header" style={{ marginTop: 8 }}>
            {t("mx.pick.title")}
          </T>
          <T v="body" tone="ink2" style={{ marginTop: 6 }}>
            {t("mx.pick.lead")}
          </T>

          <View style={{ gap: 12, marginTop: 24 }}>
            {stores.map((m) => (
              <StoreCard key={m} mode={m} onPress={() => finish(m)} />
            ))}
            <Tap
              onPress={() => finish("all")}
              accessibilityLabel={`${t("mx.pick.all")}. ${t("mx.pick.allB")}`}
              style={[styles.allCard, { borderColor: c.line, backgroundColor: c.surface2 }]}
            >
              <IconChip icon="globe" mode="all" size={40} color={c.ink2} />
              <View style={{ flex: 1 }}>
                <T v="h3">{t("mx.pick.all")}</T>
                <T v="small" tone="ink2">
                  {t("mx.pick.allB")}
                </T>
              </View>
            </Tap>
          </View>

          <View style={{ flex: 1 }} />
          <T v="small" tone="ink3" style={{ textAlign: "center", marginTop: 24 }}>
            {t("mx.pick.later")}
          </T>
        </ScrollView>
      </Animated.View>
    </Modal>
  );
}

function StoreCard({ mode, onPress }: { mode: MarketId; onPress: () => void }) {
  const { t } = useT();
  const { c } = useTheme();
  const a = useAccent(mode);
  const s = storeText(mode, t);
  const cats = s.categories
    .slice(0, 3)
    .map((x) => x.label)
    .join(" · ");
  return (
    <Tap
      onPress={onPress}
      accessibilityLabel={t("mx.pick.cardA11y", { name: s.name, source: s.source, cats })}
      style={[styles.card, { backgroundColor: c.surface, borderColor: c.line }]}
    >
      <View style={[styles.bar, { backgroundColor: a.accent }]} />
      <View style={{ flex: 1 }}>
        <StoreName mode={mode} variant="hero" size={22} />
        <T v="small" tone="ink2" style={{ fontWeight: "700" }}>
          {t("mx.pick.from", { source: s.source })}
        </T>
        <T v="small" tone="ink2" style={{ marginTop: 4 }} numberOfLines={2}>
          {cats}
        </T>
      </View>
      <Icon name="arrow-right" size={18} color={c.ink3} />
    </Tap>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", justifyContent: "flex-end" },
  skip: { minHeight: 44, minWidth: 64, paddingHorizontal: 16, borderRadius: radius.pill, alignItems: "center", justifyContent: "center" },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    minHeight: 92,
    paddingVertical: 14,
    paddingLeft: 18,
    paddingRight: 14,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    overflow: "hidden",
  },
  bar: { position: "absolute", left: 0, top: 0, bottom: 0, width: 4 },
  allCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    minHeight: 64,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderStyle: "dashed",
  },
});
