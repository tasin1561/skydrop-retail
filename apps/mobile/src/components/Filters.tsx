import { useState } from "react";
import { StyleSheet, TextInput, View } from "react-native";
import type { BrandFacet, SearchParams, Sort } from "@/api/types";
import type { StoreMode } from "@/lib/types";
import { STORES , storeText } from "@/lib/markets";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Sheet } from "./Sheet";
import { Button, T, Tap } from "./ui";
import { Icon } from "./Icon";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";

export type Filters = Pick<SearchParams, "store" | "category" | "brand" | "min" | "max" | "rating" | "sort">;

/** API limit for ?brand= (CatalogQuery::MAX_BRANDS). */
export const MAX_BRANDS = 10;
const BRANDS_SHOWN = 6;
const BRAND_SEARCH_FROM = 10;

const same = (a: string, b: string) => a.toLowerCase() === b.toLowerCase();

/** Adds or removes one brand (case-insensitive); undefined when none is left. */
export function toggleBrand(list: string[] | undefined, name: string): string[] | undefined {
  const cur = list ?? [];
  const next = cur.some((b) => same(b, name)) ? cur.filter((b) => !same(b, name)) : [...cur, name].slice(-MAX_BRANDS);
  return next.length ? next : undefined;
}

/** label is a dictionary key: show it with t(). */
export const SORTS: { id: Sort; label: MsgKey }[] = [
  { id: "relevance", label: "m.relevance" },
  { id: "price_asc", label: "f.priceAsc" },
  { id: "price_desc", label: "f.priceDesc" },
  { id: "rating", label: "f.rating" },
  { id: "discount", label: "f.discount" },
];

export function activeFilterCount(f: Filters) {
  return [f.category, f.min, f.max, f.rating, f.brand?.length].filter(Boolean).length;
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

/**
 * Brand multi-select: top brands of the current result (search facets, counts shown), 6 at first with "Show more",
 * a search box when there are more than 10. Selected brands missing from the facets stay listed so they can be unticked.
 */
export function BrandList({ brands, value, onChange, mode = "all" }: { brands: BrandFacet[]; value?: string[]; onChange: (v: string[] | undefined) => void; mode?: StoreMode }) {
  const { t, num } = useT();
  const { c } = useTheme();
  const a = useAccent(mode);
  const [more, setMore] = useState(false);
  const [query, setQuery] = useState("");
  const picked = value ?? [];
  const all: BrandFacet[] = [...picked.filter((p) => !brands.some((b) => same(b.name, p))).map((name) => ({ name, count: 0 })), ...brands];
  if (!all.length) return null;
  const isOn = (n: string) => picked.some((p) => same(p, n));
  const full = picked.length >= MAX_BRANDS;
  const q = query.trim().toLowerCase();
  const matches = q ? all.filter((b) => b.name.toLowerCase().includes(q)) : all;
  const visible = q || more ? matches : matches.filter((b, i) => i < BRANDS_SHOWN || isOn(b.name));
  const hidden = matches.length - visible.length;

  return (
    <View style={{ gap: 2 }}>
      {all.length > BRAND_SEARCH_FROM && (
        <View style={[styles.search, { backgroundColor: c.surface2 }]}>
          <Icon name="search" size={16} color={c.ink3} />
          <TextInput
            value={query}
            onChangeText={setQuery}
            placeholder={t("mx.brandSearch")}
            placeholderTextColor={c.ink3}
            accessibilityLabel={t("mx.brandSearch")}
            autoCorrect={false}
            maxLength={60}
            style={{ flex: 1, color: c.ink, fontSize: 16, minHeight: 44 }}
          />
        </View>
      )}
      {visible.map((b) => {
        const on = isOn(b.name);
        const off = !on && full;
        return (
          <Tap
            key={b.name}
            disabled={off}
            onPress={() => {
              haptic.tap();
              onChange(toggleBrand(value, b.name));
            }}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: on, disabled: off }}
            accessibilityLabel={b.count ? t("mx.brandA11y", { name: b.name, count: b.count }) : b.name}
            scaleTo={0.99}
            style={[styles.brandRow, off && { opacity: 0.45 }]}
          >
            <View style={[styles.box, { borderColor: on ? a.accent : c.line, backgroundColor: on ? a.accent : c.surface }]}>
              {on && <Icon name="check" size={13} color="#fff" />}
            </View>
            <T v="body" numberOfLines={1} style={{ flex: 1, fontWeight: on ? "700" : "500" }}>
              {b.name}
            </T>
            {b.count > 0 && (
              <T v="small" tone="ink3">
                {num(b.count)}
              </T>
            )}
          </Tap>
        );
      })}
      {!!q && !matches.length && (
        <T v="small" tone="ink3" style={{ paddingVertical: 8 }}>
          {t("mx.brandNone", { q: query.trim() })}
        </T>
      )}
      {full && (
        <T v="tiny" tone="ink3" style={{ paddingTop: 4 }}>
          {t("mx.brandMax", { count: MAX_BRANDS })}
        </T>
      )}
      {!q && matches.length > BRANDS_SHOWN && (hidden > 0 || more) && (
        <Tap onPress={() => setMore(!more)} accessibilityRole="button" accessibilityState={{ expanded: more }} style={styles.more}>
          <T v="small" style={{ color: a.ink, fontWeight: "700" }}>
            {more ? t("mx.brandLess") : t("mx.brandMore", { count: hidden })}
          </T>
          <Icon name={more ? "chevron-up" : "chevron-down"} size={16} color={a.ink} />
        </Tap>
      )}
    </View>
  );
}

/** Brand-only sheet (category screen). Applies on "Show results", like the full filter sheet. */
export function BrandSheet({ open, onClose, value, onApply, brands, mode }: { open: boolean; onClose: () => void; value?: string[]; onApply: (v: string[] | undefined) => void; brands: BrandFacet[]; mode: StoreMode }) {
  const { t } = useT();
  const [v, setV] = useState<string[] | undefined>(value);
  const [wasOpen, setWasOpen] = useState(open);
  if (open !== wasOpen) {
    setWasOpen(open);
    if (open) setV(value);
  }
  return (
    <Sheet
      open={open}
      onClose={onClose}
      title={t("mx.brand")}
      footer={
        <View style={{ flexDirection: "row", gap: 10 }}>
          <Button label={t("mx.brandClear")} kind="ghost" onPress={() => setV(undefined)} style={{ flex: 1 }} />
          <Button
            label={t("m.showResults")}
            mode={mode}
            onPress={() => {
              haptic.light();
              onApply(v);
              onClose();
            }}
            style={{ flex: 2 }}
          />
        </View>
      }
    >
      <BrandList brands={brands} value={v} onChange={setV} mode={mode} />
    </Sheet>
  );
}

export function FilterSheet({
  open,
  onClose,
  value,
  onApply,
  stores,
  brands = [],
}: {
  open: boolean;
  onClose: () => void;
  value: Filters;
  onApply: (f: Filters) => void;
  stores: StoreMode[];
  /** top brands of the current result (search facets) */
  brands?: BrandFacet[];
}) {
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
      {(brands.length > 0 || !!f.brand?.length) && (
        <View style={{ gap: 6, marginBottom: 18 }}>
          <T v="label" tone="ink3">
            {t("mx.brand")}
          </T>
          <BrandList brands={brands} value={f.brand} onChange={(b) => setF({ ...f, brand: b })} mode={mode} />
        </View>
      )}
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
  brandRow: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 44, paddingHorizontal: 4, borderRadius: 10 },
  box: { width: 22, height: 22, borderRadius: 6, borderWidth: 2, alignItems: "center", justifyContent: "center" },
  search: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, borderRadius: radius.sm, marginBottom: 4 },
  more: { flexDirection: "row", alignItems: "center", gap: 4, minHeight: 44, alignSelf: "flex-start", paddingHorizontal: 4 },
});
