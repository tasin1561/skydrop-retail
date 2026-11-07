// All variants as swatches (when images exist) or chips, grouped by dimension.
import { memo, useState } from "react";
import { StyleSheet, View } from "react-native";
import Animated, { ZoomIn } from "react-native-reanimated";
import { Image } from "expo-image";
import type { DimGroup } from "@/lib/variants";
import { sized } from "@/lib/variants";
import type { MarketId } from "@/lib/types";
import { useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "../Icon";
import { T, Tap } from "../ui";
import { useT } from "@/i18n";

export const VariantGroup = memo(function VariantGroup({
  group,
  selected,
  isAvailable,
  onPick,
  market,
}: {
  group: DimGroup;
  selected: string | undefined;
  isAvailable: (value: string) => boolean;
  onPick: (value: string) => void;
  market: MarketId;
}) {
  const { t } = useT();
  const { c } = useTheme();
  const a = useAccent(market);
  const swatches = group.values.some((v) => v.swatch);
  const LIMIT = swatches ? 12 : 16;
  const [expanded, setExpanded] = useState(false);
  const selIdx = group.values.findIndex((v) => v.value === selected);
  const shown = expanded || group.values.length <= LIMIT ? group.values : group.values.slice(0, Math.max(LIMIT, selIdx + 1));

  return (
    <View style={{ gap: 8 }}>
      <T v="small">
        <T v="small" tone="ink2" style={{ fontWeight: "600" }}>
          {group.key}:{" "}
        </T>
        <T v="small" style={{ fontWeight: "800" }}>
          {selected ?? t("var.choose")}
        </T>
      </T>
      <View style={styles.wrap} accessibilityRole="radiogroup" accessibilityLabel={group.key}>
        {shown.map((v) => {
          const on = v.value === selected;
          const combo = isAvailable(v.value);
          const press = () => {
            if (on) return;
            haptic.tap();
            onPick(v.value);
          };
          const label = `${group.key} ${v.value}${!combo ? t("var.otherChange") : ""}`;
          if (swatches)
            return (
              <Tap key={v.value} onPress={press} accessibilityRole="radio" accessibilityState={{ checked: on }} accessibilityLabel={label} style={{ width: 72, alignItems: "center", gap: 4 }}>
                <View style={[styles.swatch, { borderColor: on ? a.accent : c.line, borderWidth: on ? 2.5 : 1, opacity: combo ? 1 : 0.55 }]}>
                  {v.swatch ? (
                    <Image source={{ uri: sized(v.swatch, "SS112") ?? v.swatch }} style={{ width: "100%", height: "100%" }} contentFit="cover" />
                  ) : (
                    <T v="small" style={{ fontWeight: "800", color: "#3a3a44" }}>
                      {v.value.slice(0, 2)}
                    </T>
                  )}
                  {on && (
                    <Animated.View entering={ZoomIn.springify()} style={[styles.check, { backgroundColor: a.accent }]}>
                      <Icon name="check" size={10} color="#fff" />
                    </Animated.View>
                  )}
                </View>
                <T v="tiny" numberOfLines={2} style={{ textAlign: "center", color: on ? a.ink : c.ink2, fontWeight: on ? "800" : "500" }}>
                  {v.value}
                </T>
              </Tap>
            );
          return (
            <Tap
              key={v.value}
              onPress={press}
              accessibilityRole="radio"
              accessibilityState={{ checked: on }}
              accessibilityLabel={label}
              style={[styles.chip, { backgroundColor: on ? a.soft : c.surface2, borderColor: on ? a.accent : "transparent" }]}
            >
              <T v="small" style={{ fontWeight: "700", color: on ? a.ink : combo ? c.ink : c.ink3 }}>
                {v.value}
              </T>
            </Tap>
          );
        })}
        {group.values.length > shown.length && (
          <Tap onPress={() => setExpanded(true)} accessibilityLabel={t("m.showMore", { count: group.values.length - shown.length })} style={[styles.chip, { backgroundColor: "transparent", borderColor: c.line }]}>
            <T v="small" style={{ fontWeight: "700", color: a.ink }}>
              {t("var.more", { count: group.values.length - shown.length })}
            </T>
          </Tap>
        )}
      </View>
    </View>
  );
});

const styles = StyleSheet.create({
  wrap: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  swatch: { width: 60, height: 60, borderRadius: 14, overflow: "hidden", backgroundColor: "#fff", alignItems: "center", justifyContent: "center" },
  check: { position: "absolute", top: 3, right: 3, width: 16, height: 16, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  chip: { minHeight: 44, minWidth: 52, paddingHorizontal: 14, borderRadius: 14, alignItems: "center", justifyContent: "center", borderWidth: 1.5 },
});
