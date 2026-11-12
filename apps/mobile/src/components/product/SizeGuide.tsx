import { useState } from "react";
import { ScrollView, StyleSheet, View } from "react-native";
import { SIZE_CHARTS, sizeValue, type SizeChartId } from "@/lib/sizeGuide";
import type { StoreMode } from "@/lib/types";
import { useT } from "@/i18n";
import { haptic } from "@/lib/haptics";
import { useAccent, useTheme } from "@/theme";
import { Icon } from "../Icon";
import { Sheet } from "../Sheet";
import { T, Tap } from "../ui";

/** "Size guide" link beside the size options (same charts as the website): a bottom sheet, inches or cm. */
export function SizeGuide({ initial = "women", mode = "all" }: { initial?: SizeChartId; mode?: StoreMode }) {
  const { t, digits } = useT();
  const { c } = useTheme();
  const a = useAccent(mode);
  const [open, setOpen] = useState(false);
  const [tab, setTab] = useState<SizeChartId>(initial);
  const [cm, setCm] = useState(false);
  const chart = SIZE_CHARTS.find((x) => x.id === tab) ?? SIZE_CHARTS[0];

  return (
    <>
      <Tap
        onPress={() => {
          haptic.tap();
          setOpen(true);
        }}
        accessibilityRole="button"
        accessibilityLabel={t("sg.open")}
        hitSlop={10}
        style={{ flexDirection: "row", alignItems: "center", gap: 5, minHeight: 32 }}
      >
        <Icon name="maximize-2" size={14} color={a.ink} />
        <T v="small" style={{ color: a.ink, fontWeight: "700" }}>
          {t("sg.open")}
        </T>
      </Tap>
      <Sheet open={open} onClose={() => setOpen(false)} title={t("sg.title")}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8, paddingBottom: 10 }} accessibilityRole="tablist">
          {SIZE_CHARTS.map((x) => {
            const on = x.id === tab;
            return (
              <Tap
                key={x.id}
                onPress={() => setTab(x.id)}
                accessibilityRole="tab"
                accessibilityState={{ selected: on }}
                style={[styles.chip, { backgroundColor: on ? a.accent : c.surface, borderColor: on ? a.accent : c.line }]}
              >
                <T v="small" style={{ fontWeight: "700", color: on ? "#fff" : c.ink }}>
                  {t(x.label)}
                </T>
              </Tap>
            );
          })}
        </ScrollView>
        <View style={{ flexDirection: "row", justifyContent: "flex-end", gap: 6, marginBottom: 8 }}>
          {[false, true].map((v) => (
            <Tap
              key={String(v)}
              onPress={() => setCm(v)}
              accessibilityRole="button"
              accessibilityState={{ selected: cm === v }}
              style={[styles.unit, { backgroundColor: cm === v ? c.ink : c.surface2 }]}
            >
              <T v="tiny" style={{ fontWeight: "700", color: cm === v ? c.bg : c.ink2 }}>
                {t(v ? "sg.cm" : "sg.in")}
              </T>
            </Tap>
          ))}
        </View>
        <View style={[styles.table, { borderColor: c.line }]}>
          <View style={[styles.row, { backgroundColor: c.surface2 }]}>
            {chart.cols.map((k) => (
              <T key={k} v="tiny" tone="ink2" style={styles.cell}>
                {t(k)}
              </T>
            ))}
          </View>
          {chart.rows.map((r) => (
            <View key={String(r[0])} style={[styles.row, { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: c.line }]}>
              {r.map((v, i) => (
                <T key={i} v="small" style={[styles.cell, { fontWeight: i === 0 ? "800" : "500" }]}>
                  {digits(sizeValue(v, chart.body[i], cm))}
                </T>
              ))}
            </View>
          ))}
        </View>
        <View style={{ gap: 6, marginTop: 12 }}>
          {(["sg.tipBetween", "sg.tipBrand"] as const).map((k) => (
            <View key={k} style={{ flexDirection: "row", gap: 8 }}>
              <Icon name="info" size={14} color={a.ink} />
              <T v="tiny" tone="ink2" style={{ flex: 1, lineHeight: 17 }}>
                {t(k)}
              </T>
            </View>
          ))}
        </View>
      </Sheet>
    </>
  );
}

const styles = StyleSheet.create({
  chip: { minHeight: 40, paddingHorizontal: 14, borderRadius: 999, borderWidth: 1, justifyContent: "center" },
  unit: { minHeight: 34, paddingHorizontal: 12, borderRadius: 999, justifyContent: "center" },
  table: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 12, overflow: "hidden" },
  row: { flexDirection: "row", paddingVertical: 10 },
  cell: { flex: 1, textAlign: "center", fontWeight: "700" },
});
