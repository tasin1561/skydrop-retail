// Segmented store switcher with a sliding pill indicator (translateX + scaleX on the UI thread).
import { useEffect, useState } from "react";
import { Pressable, StyleSheet, View, type LayoutChangeEvent } from "react-native";
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from "react-native-reanimated";
import type { StoreMode } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { ACCENTS, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { T } from "./ui";
import { useT } from "@/i18n";

export function StoreSwitcher({
  value,
  onChange,
  modes,
  compact,
}: {
  value: StoreMode;
  onChange: (m: StoreMode) => void;
  modes: StoreMode[];
  compact?: boolean;
}) {
  const { t } = useT();
  const { c } = useTheme();
  const [layouts, setLayouts] = useState<Record<string, { x: number; w: number }>>({});
  const x = useSharedValue(0);
  const w = useSharedValue(0);

  useEffect(() => {
    const l = layouts[value];
    if (!l) return;
    const spring = { damping: 20, stiffness: 240, mass: 0.8 };
    x.value = withSpring(l.x, spring);
    w.value = withSpring(l.w, spring);
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

  return (
    <View style={[styles.track, { backgroundColor: c.surface2 }]} accessibilityRole="tablist">
      <Animated.View style={[styles.pill, { backgroundColor: ACCENTS[value].accent }, pill]} />
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
            <T v="small" numberOfLines={1} style={{ fontWeight: "700", color: on ? "#fff" : c.ink2 }}>
              {m === "all" ? t(compact ? "store.all.short" : "store.all.name") : compact ? STORES[m].short : STORES[m].name}
            </T>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  track: { flexDirection: "row", borderRadius: 999, padding: 4 },
  tab: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", paddingHorizontal: 6, zIndex: 1 },
  pill: { position: "absolute", top: 4, bottom: 4, left: 0, width: 100, borderRadius: 999 },
});
