import { useEffect, useState } from "react";
import { StyleSheet, TextInput, View } from "react-native";
import type { SearchParams, Sort } from "@/api/types";
import type { StoreMode } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Sheet } from "./Sheet";
import { Button, T, Tap } from "./ui";
import { Icon } from "./Icon";

export type Filters = Pick<SearchParams, "store" | "category" | "min" | "max" | "rating" | "sort">;

export const SORTS: { id: Sort; label: string }[] = [
  { id: "relevance", label: "Relevance" },
  { id: "price_asc", label: "Price: low to high" },
  { id: "price_desc", label: "Price: high to low" },
  { id: "rating", label: "Top rated" },
  { id: "discount", label: "Biggest discount" },
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
  const { c } = useTheme();
  const [f, setF] = useState<Filters>(value);
  useEffect(() => {
    if (open) setF(value);
  }, [open, value]);
  const mode = f.store ?? "all";
  const cats = STORES.all.categories;

  const section = (title: string, children: React.ReactNode) => (
    <View style={{ gap: 10, marginBottom: 18 }}>
      <T v="label" tone="ink3">
        {title}
      </T>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>{children}</View>
    </View>
  );

  const num = (v: string) => {
    const n = parseInt(v.replace(/\D/g, ""), 10);
    return Number.isFinite(n) && n > 0 ? n : undefined;
  };

  return (
    <Sheet
      open={open}
      onClose={onClose}
      title="Filters"
      footer={
        <View style={{ flexDirection: "row", gap: 10 }}>
          <Button label="Reset" kind="ghost" onPress={() => setF({ store: f.store, sort: f.sort })} style={{ flex: 1 }} />
          <Button
            label="Show results"
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
        "Store",
        stores.map((s) => <Chip key={s} mode={s} label={s === "all" ? "All stores" : `${STORES[s].name} (${STORES[s].source})`} on={mode === s} onPress={() => setF({ ...f, store: s })} />),
      )}
      {section("Category", [
        <Chip key="any" label="Any" mode={mode} on={!f.category} onPress={() => setF({ ...f, category: undefined })} />,
        ...cats.map((cat) => <Chip key={cat.slug} mode={mode} label={cat.label} on={f.category === cat.slug} onPress={() => setF({ ...f, category: cat.slug })} />),
      ])}
      <View style={{ gap: 10, marginBottom: 18 }}>
        <T v="label" tone="ink3">
          Price (BDT)
        </T>
        <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
          {(["min", "max"] as const).map((k) => (
            <TextInput
              key={k}
              value={f[k] ? String(f[k]) : ""}
              onChangeText={(t) => setF({ ...f, [k]: num(t) })}
              placeholder={k === "min" ? "Min ৳" : "Max ৳"}
              placeholderTextColor={c.ink3}
              keyboardType="number-pad"
              accessibilityLabel={k === "min" ? "Minimum price in taka" : "Maximum price in taka"}
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
              label={lo && hi ? `৳${lo.toLocaleString()}-${hi.toLocaleString()}` : lo ? `Over ৳${lo.toLocaleString()}` : `Under ৳${hi!.toLocaleString()}`}
              on={f.min === lo && f.max === hi}
              onPress={() => setF({ ...f, min: lo, max: hi })}
            />
          ))}
        </View>
      </View>
      {section(
        "Rating",
        [undefined, 3, 4, 4.5].map((r) => <Chip key={String(r)} mode={mode} label={r ? `${r}★ & up` : "Any"} on={f.rating === r} onPress={() => setF({ ...f, rating: r })} />),
      )}
      {section(
        "Sort by",
        SORTS.map((s) => <Chip key={s.id} mode={mode} label={s.label} on={(f.sort ?? "relevance") === s.id} onPress={() => setF({ ...f, sort: s.id })} />),
      )}
    </Sheet>
  );
}

export function SortSheet({ open, onClose, value, onPick, mode }: { open: boolean; onClose: () => void; value: Sort; onPick: (s: Sort) => void; mode: StoreMode }) {
  const { c } = useTheme();
  const a = useAccent(mode);
  return (
    <Sheet open={open} onClose={onClose} title="Sort by">
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
            accessibilityLabel={s.label}
            scaleTo={0.98}
            style={[styles.row, on && { backgroundColor: c.surface2 }]}
          >
            <T v="body" style={{ flex: 1, fontWeight: on ? "800" : "500" }}>
              {s.label}
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
