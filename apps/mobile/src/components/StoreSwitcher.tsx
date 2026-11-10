// Segmented store switcher with a sliding pill indicator (translateX + scaleX on the UI thread).
import { useEffect, useState } from "react";
import { Pressable, StyleSheet, View, type LayoutChangeEvent } from "react-native";
import Animated, { useAnimatedStyle, useSharedValue, withTiming } from "react-native-reanimated";
import type { StoreMode } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { STORE_TEXT, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { T } from "./ui";
import { StoreName } from "./StoreName";
import { useT } from "@/i18n";
import { DUR, timing } from "@/lib/motion";
import { useFeature } from "@/lib/features";

export function StoreSwitcher({
  value,
  onChange,
  modes: allModes,
  compact,
  label,
}: {
  value: StoreMode;
  onChange: (m: StoreMode) => void;
  modes: StoreMode[];
  compact?: boolean;
  /** Show the "Shopping at" caption above the control (home header). */
  label?: boolean;
}) {
  const { t } = useT();
  const { c } = useTheme();
  // Admin store switches (feature store.*) hide a store everywhere, on top of the marketplaces list.
  const flags: Record<string, boolean> = {
    amazon: useFeature("store.amazon"),
    flipkart: useFeature("store.flipkart"),
    myntra: useFeature("store.myntra"),
  };
  const modes = allModes.filter((m) => m === "all" || flags[m] !== false);
  const [layouts, setLayouts] = useState<Record<string, { x: number; w: number }>>({});
  const x = useSharedValue(0);
  const w = useSharedValue(0);

  useEffect(() => {
    const l = layouts[value];
    if (!l) return;
    x.value = withTiming(l.x, timing(DUR.open));
    w.value = withTiming(l.w, timing(DUR.open));
  }, [value, layouts, x, w]);

  // The pill is 100px wide at rest and scaled to the tab width (transform only, no layout work).
  const pill = useAnimatedStyle(() => ({
    transform: [{ translateX: x.value + (w.value - 100) / 2 }, { scaleX: w.value / 100 }],
    opacity: w.value > 0 ? 1 : 0,
  }));

  const onLayout = (m: StoreMode) => (e: LayoutChangeEvent) => {
    const { x: lx, width } = e.nativeEvent.layout;
    setLayouts((prev) => (prev[m]?.x === lx && prev[m]?.w === width ? prev : { ...prev, [m]: { x: lx, w: width } }));
  };

  const track = (
    <View
      style={[styles.track, { backgroundColor: c.surface2 }]}
      accessibilityRole="tablist"
      accessibilityLabel={label ? t("mx.shoppingAt") : undefined}
    >
      <Animated.View style={[styles.pill, { backgroundColor: STORE_TEXT[value].fill[0] }, pill]} />
      {modes.map((m) => {
        const on = m === value;
        return (
          <Pressable
            key={m}
            onLayout={onLayout(m)}
            onPress={() => {
              if (on) return;
              haptic.tap();
              onChange(m);
            }}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            accessibilityLabel={m === "all" ? t("store.all.name") : t("m.storeFromA11y", { name: STORES[m].name, source: STORES[m].source })}
            style={styles.tab}
          >
            <StoreName mode={m} size={13} fill={on} name={m === "all" ? t(compact ? "store.all.short" : "store.all.name") : compact ? STORES[m].short : STORES[m].name} />
          </Pressable>
        );
      })}
    </View>
  );
  if (!label) return track;
  return (
    <View>
      <T v="small" tone="ink2" style={styles.caption} importantForAccessibility="no" accessibilityElementsHidden>
        {t("mx.shoppingAt")}
      </T>
      {track}
    </View>
  );
}

const styles = StyleSheet.create({
  track: { flexDirection: "row", borderRadius: 999, padding: 4 },
  caption: { fontWeight: "600", marginBottom: 6, marginLeft: 4 },
  tab: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", paddingHorizontal: 6, zIndex: 1 },
  pill: { position: "absolute", top: 4, bottom: 4, left: 0, width: 100, borderRadius: 999 },
});
