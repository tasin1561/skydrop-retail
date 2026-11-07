import { useState } from "react";
import { StyleSheet, TextInput, View } from "react-native";
import type { SearchParams, Sort } from "@/api/types";
import type { StoreMode } from "@/lib/types";
import { STORES , storeText } from "@/lib/markets";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Sheet } from "./Sheet";
import { Button, T, Tap } from "./ui";
import { Icon } from "./Icon";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";

export type Filters = Pick<SearchParams, "store" | "category" | "min" | "max" | "rating" | "sort">;

/** label is a dictionary key: show it with t(). */
export const SORTS: { id: Sort; label: MsgKey }[] = [
  { id: "relevance", label: "m.relevance" },
  { id: "price_asc", label: "f.priceAsc" },
  { id: "price_desc", label: "f.priceDesc" },
  { id: "rating", label: "f.rating" },
  { id: "discount", label: "f.discount" },
];

export function activeFilterCount(f: Filters) {
  return [f.category, f.min, f.max, f.rating].filter(Boolean).length;
}

function Chip({ label, on, onPress, mode = "all" }: { label: string; on: boolean; onPress: () => void; mode?: StoreMode }) {
  const { c } = useTheme();
  const a = useAccent(mode);
  return (
    <Tap
      onPress={() => {
        haptic.tap();
        onPress();
      }}
      accessibilityRole="radio"
      accessibilityState={{ checked: on }}
      accessibilityLabel={label}
      style={[styles.chip, { backgroundColor: on ? a.soft : c.surface2, borderColor: on ? a.accent : "transparent" }]}
    >
      <T v="small" style={{ fontWeight: "700", color: on ? a.ink : c.ink }}>
        {label}
      </T>
    </Tap>
  );
}

export function FilterSheet({ open, onClose, value, onApply, stores }: { open: boolean; onClose: () => void; value: Filters; onApply: (f: Filters) => void; stores: StoreMode[] }) {
  const { t, bdt } = useT();
  const { c } = useTheme();
  const [f, setF] = useState<Filters>(value);
  const [wasOpen, setWasOpen] = useState(open);
  if (open !== wasOpen) {
    setWasOpen(open);
    if (open) setF(value);
  }
  const mode = f.store ?? "all";
  const cats = storeText("all", t).categories;

  const section = (title: string, children: React.ReactNode) => (
    <View style={{ gap: 10, marginBottom: 18 }}>
      <T v="label" tone="ink3">
        {title}
      </T>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>{children}</View>
    </View>
  );

  const toNum = (v: string) => {
    const n = parseInt(v.replace(/\D/g, ""), 10);
    return Number.isFinite(n) && n > 0 ? n : undefined;
  };

  return (
    <Sheet
      open={open}
      onClose={onClose}
      title={t("f.filters")}
      footer={
        <View style={{ flexDirection: "row", gap: 10 }}>
          <Button label={t("m.reset")} kind="ghost" onPress={() => setF({ store: f.store, sort: f.sort })} style={{ flex: 1 }} />
          <Button
            label={t("m.showResults")}
            mode={mode}
            onPress={() => {
              haptic.light();
              onApply(f);
              onClose();
            }}
            style={{ flex: 2 }}
          />
        </View>
      }
    >
      {section(
        t("f.store"),
        stores.map((s) => <Chip key={s} mode={s} label={s === "all" ? t("footer.allStores") : `${STORES[s].name} (${STORES[s].source})`} on={mode === s} onPress={() => setF({ ...f, store: s })} />),
      )}
      {section(t("f.category"), [
        <Chip key="any" label={t("m.any")} mode={mode} on={!f.category} onPress={() => setF({ ...f, category: undefined })} />,
        ...cats.map((cat) => <Chip key={cat.slug} mode={mode} label={cat.label} on={f.category === cat.slug} onPress={() => setF({ ...f, category: cat.slug })} />),
      ])}
      <View style={{ gap: 10, marginBottom: 18 }}>
        <T v="label" tone="ink3">{t("m.priceBdt")}</T>
        <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
          {(["min", "max"] as const).map((k) => (
            <TextInput
              key={k}
              value={f[k] ? String(f[k]) : ""}
              onChangeText={(v) => setF({ ...f, [k]: toNum(v) })}
              placeholder={k === "min" ? t("m.minPh") : t("m.maxPh")}
              placeholderTextColor={c.ink3}
              keyboardType="number-pad"
              accessibilityLabel={k === "min" ? t("m.minA11y") : t("m.maxA11y")}
              style={[styles.input, { color: c.ink, backgroundColor: c.surface2 }]}
            />
          ))}
        </View>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
          {[
            [undefined, 1000],
            [1000, 5000],
            [5000, 15000],
            [15000, undefined],
          ].map(([lo, hi]) => (
            <Chip
              key={`${lo}-${hi}`}
              mode={mode}
              label={lo && hi ? t("f.range", { a: bdt(lo), b: bdt(hi) }) : lo ? t("f.over", { amount: bdt(lo) }) : t("f.under", { amount: bdt(hi!) })}
              on={f.min === lo && f.max === hi}
              onPress={() => setF({ ...f, min: lo, max: hi })}
            />
          ))}
        </View>
      </View>
      {section(
        t("f.ratingLegend"),
        [undefined, 3, 4, 4.5].map((r) => <Chip key={String(r)} mode={mode} label={r ? t("m.starsUp", { rating: r }) : t("m.any")} on={f.rating === r} onPress={() => setF({ ...f, rating: r })} />),
      )}
      {section(
        t("f.sortBy"),
        SORTS.map((s) => <Chip key={s.id} mode={mode} label={t(s.label)} on={(f.sort ?? "relevance") === s.id} onPress={() => setF({ ...f, sort: s.id })} />),
      )}
    </Sheet>
  );
}

export function SortSheet({ open, onClose, value, onPick, mode }: { open: boolean; onClose: () => void; value: Sort; onPick: (s: Sort) => void; mode: StoreMode }) {
  const { t } = useT();
  const { c } = useTheme();
  const a = useAccent(mode);
  return (
    <Sheet open={open} onClose={onClose} title={t("f.sortBy")}>
      {SORTS.map((s) => {
        const on = value === s.id;
        return (
          <Tap
            key={s.id}
            onPress={() => {
              haptic.tap();
              onPick(s.id);
              onClose();
            }}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={t(s.label)}
            scaleTo={0.98}
            style={[styles.row, on && { backgroundColor: c.surface2 }]}
          >
            <T v="body" style={{ flex: 1, fontWeight: on ? "800" : "500" }}>
              {t(s.label)}
            </T>
            {on && <Icon name="check" size={18} color={a.accent} />}
          </Tap>
        );
      })}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  chip: { minHeight: 44, paddingHorizontal: 14, borderRadius: 14, justifyContent: "center", borderWidth: 1.5 },
  input: { flex: 1, minHeight: 48, borderRadius: radius.sm, paddingHorizontal: 12, fontSize: 16 },
  row: { flexDirection: "row", alignItems: "center", minHeight: 52, paddingHorizontal: 12, borderRadius: 12 },
});
