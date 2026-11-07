// EN | বাংলা segmented toggle (Account tab). The pill slides with a transform only; with Reduce Motion it
// jumps. Each option is a 44 pt target and reports its selected state to screen readers.
import { useState } from "react";
import { Text, View, StyleSheet, type LayoutChangeEvent } from "react-native";
import Animated, { Easing, useAnimatedStyle, useReducedMotion, withTiming } from "react-native-reanimated";
import { useLang, useT, type Lang } from "@/i18n";
import { useFeature } from "@/lib/features";
import { haptic } from "@/lib/haptics";
import { fontFor } from "@/theme/fonts";
import { shadow, useTheme } from "@/theme";
import { Tap } from "./ui";

const OPTIONS: { id: Lang; label: string }[] = [
  { id: "en", label: "EN" },
  { id: "bn", label: "বাংলা" },
];

export function LangSwitch() {
  const on = useFeature("bangla");
  const { lang, setLang } = useLang();
  const { t } = useT();
  const { c } = useTheme();
  const reduce = useReducedMotion();
  const [w, setW] = useState(0);
  const idx = Math.max(0, OPTIONS.findIndex((o) => o.id === lang));
  const pill = useAnimatedStyle(() => {
    const x = idx * w;
    return { transform: [{ translateX: reduce ? x : withTiming(x, { duration: 220, easing: Easing.out(Easing.cubic) }) }] };
  }, [idx, w, reduce]);
  if (!on) return null;

  return (
    <View
      accessibilityRole="radiogroup"
      accessibilityLabel={t("lang.label")}
      style={[styles.wrap, { backgroundColor: c.surface2 }]}
      onLayout={(e: LayoutChangeEvent) => setW((e.nativeEvent.layout.width - 8) / 2)}
    >
      {w > 0 && <Animated.View pointerEvents="none" style={[styles.pill, { width: w, backgroundColor: c.surface }, shadow(c), pill]} />}
      {OPTIONS.map((o) => {
        const sel = lang === o.id;
        return (
          <Tap
            key={o.id}
            onPress={() => {
              if (sel) return;
              haptic.tap();
              setLang(o.id);
            }}
            accessibilityRole="radio"
            accessibilityState={{ selected: sel, checked: sel }}
            accessibilityLabel={o.id === "en" ? "English" : "বাংলা"}
            accessibilityLanguage={o.id === "en" ? "en" : "bn"}
            style={styles.item}
            scaleTo={0.97}
          >
            <Text style={[{ fontSize: 14, color: sel ? c.ink : c.ink3 }, fontFor(o.id, "700"), !fontFor(o.id, "700").fontFamily && { fontWeight: "700" }]}>{o.label}</Text>
          </Tap>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: "row", borderRadius: 999, padding: 4, width: 168, position: "relative" },
  pill: { position: "absolute", top: 4, bottom: 4, left: 4, borderRadius: 999 },
  item: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 999 },
});
