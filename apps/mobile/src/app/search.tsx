import { useEffect, useMemo, useRef, useState } from "react";
import { Keyboard, Pressable, StyleSheet, TextInput, View, type TextInputInstance } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeIn, FadeOut } from "react-native-reanimated";
import { api } from "@/api/client";
import type { Sort, Suggestion } from "@/api/types";
import type { StoreMode } from "@/lib/types";
import { STORES, isStoreMode , storeText } from "@/lib/markets";
import { useStore } from "@/state/store";
import { radius, shadow, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { IconButton, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { StoreSwitcher } from "@/components/StoreSwitcher";
import { ProductGrid } from "@/components/ProductGrid";
import { FilterSheet, SORTS, SortSheet, activeFilterCount, type Filters } from "@/components/Filters";
import { useT } from "@/i18n";

export default function Search() {
  const { t, digits } = useT();
  const params = useLocalSearchParams<{ q?: string; store?: string; category?: string; sort?: string; focus?: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { enabled, mode: homeMode } = useStore();
  const [text, setText] = useState(params.q ?? "");
  const [q, setQ] = useState(params.q ?? "");
  const [f, setF] = useState<Filters>({
    store: isStoreMode(params.store) ? params.store : homeMode,
    category: params.category || undefined,
    sort: (SORTS.find((s) => s.id === params.sort)?.id ?? "relevance") as Sort,
  });
  const [sug, setSug] = useState<{ term: string; items: Suggestion[] }>({ term: "", items: [] });
  const [focused, setFocused] = useState(params.focus === "1");
  const [filterOpen, setFilterOpen] = useState(false);
  const [sortOpen, setSortOpen] = useState(false);
  const [total, setTotal] = useState<number | null>(null);
  const input = useRef<TextInputInstance>(null);
  const mode: StoreMode = f.store ?? "all";
  const a = useAccent(mode);

  // Debounced suggestions (local DB only on the server).
  useEffect(() => {
    const term = text.trim();
    if (term.length < 2 || term === q) return;
    const timer = setTimeout(() => {
      api
        .suggest(term, mode)
        .then((items) => setSug({ term, items }))
        .catch(() => setSug({ term, items: [] }));
    }, 220);
    return () => clearTimeout(timer);
  }, [text, mode, q]);

  const submit = (term = text) => {
    Keyboard.dismiss();
    setQ(term.trim());
    setText(term.trim());
  };

  const term = text.trim();
  const sugs = term.length >= 2 && term !== q && sug.term === term ? sug.items : [];
  const searchParams = useMemo(() => ({ q, ...f }), [q, f]);
  const nFilters = activeFilterCount(f);
  const catLabel = f.category ? storeText("all", t).categories.find((x) => x.slug === f.category)?.label : null;

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 6 }}>
      <View style={styles.top}>
        <IconButton icon="arrow-left" label={t("common.back")} onPress={() => router.back()} bg={c.surface2} />
        <View style={[styles.field, { backgroundColor: c.surface, borderColor: focused ? a.accent : c.line }]}>
          <Icon name="search" size={18} color={c.ink3} />
          <TextInput
            ref={input}
            value={text}
            onChangeText={setText}
            placeholder={mode === "all" ? t("m.searchAllStores") : t("m.searchIn", { name: STORES[mode].source })}
            placeholderTextColor={c.ink3}
            autoFocus={params.focus === "1"}
            returnKeyType="search"
            onSubmitEditing={() => submit()}
            onFocus={() => setFocused(true)}
            onBlur={() => setFocused(false)}
            autoCorrect={false}
            accessibilityLabel={t("cat.searchProducts")}
            style={{ flex: 1, color: c.ink, fontSize: 16, minHeight: 46 }}
          />
          {!!text && (
            <Pressable
              onPress={() => {
                setText("");
                setQ("");
                input.current?.focus();
              }}
              accessibilityLabel={t("m.clearSearch")}
              hitSlop={12}
            >
              <Icon name="x-circle" size={18} color={c.ink3} />
            </Pressable>
          )}
        </View>
      </View>

      <View style={{ paddingHorizontal: 16, marginBottom: 10 }}>
        <StoreSwitcher value={mode} onChange={(m) => setF({ ...f, store: m })} modes={enabled} compact />
      </View>

      <View style={styles.tools}>
        <Tap onPress={() => setFilterOpen(true)} accessibilityLabel={nFilters ? t("m.filtersActive", { count: nFilters }) : t("f.filters")} style={[styles.tool, { backgroundColor: nFilters ? a.soft : c.surface, borderColor: nFilters ? a.accent : c.line }]}>
          <Icon name="sliders" size={16} color={nFilters ? a.ink : c.ink} />
          <T v="small" style={{ fontWeight: "700", color: nFilters ? a.ink : c.ink }}>
            {t("f.filters")}
            {nFilters ? ` (${digits(nFilters)})` : ""}
          </T>
        </Tap>
        <Tap onPress={() => setSortOpen(true)} accessibilityLabel={t("m.sort")} style={[styles.tool, { backgroundColor: c.surface, borderColor: c.line }]}>
          <Icon name="bar-chart" size={16} color={c.ink} />
          <T v="small" style={{ fontWeight: "700" }} numberOfLines={1}>
            {t(SORTS.find((s) => s.id === (f.sort ?? "relevance"))?.label ?? "m.relevance")}
          </T>
        </Tap>
        <View style={{ flex: 1 }} />
        {total !== null && (
          <T v="tiny" tone="ink3">
            {t("m.results", { count: total })}
          </T>
        )}
      </View>
      {catLabel && (
        <View style={{ paddingHorizontal: 16, marginBottom: 8, flexDirection: "row" }}>
          <Tap onPress={() => setF({ ...f, category: undefined })} accessibilityLabel={t("m.removeCat", { name: catLabel })} style={[styles.tag, { backgroundColor: a.soft }]}>
            <T v="small" style={{ color: a.ink, fontWeight: "700" }}>
              {catLabel}
            </T>
            <Icon name="x" size={14} color={a.ink} />
          </Tap>
        </View>
      )}

      <ProductGrid params={searchParams} onTotal={setTotal} />

      {sugs.length > 0 && focused && (
        <Animated.View entering={FadeIn.duration(140)} exiting={FadeOut.duration(120)} style={[styles.sugs, { top: insets.top + 64, backgroundColor: c.surface }, shadow(c, 2)]}>
          {sugs.map((s) => (
            <Pressable
              key={s.id}
              onPress={() => {
                haptic.tap();
                Keyboard.dismiss();
                router.push(`/product/${s.id}`);
              }}
              accessibilityRole="button"
              accessibilityLabel={t("m.cardA11y", { title: s.title, price: "", source: STORES[s.market].source }).replace(", , ", ", ")}
              style={({ pressed }) => [styles.sug, pressed && { backgroundColor: c.surface2 }]}
            >
              <Icon name="search" size={15} color={c.ink3} />
              <T v="small" numberOfLines={1} style={{ flex: 1 }}>
                {s.title}
              </T>
              <T v="tiny" tone="ink3">
                {STORES[s.market].source}
              </T>
            </Pressable>
          ))}
          <Pressable onPress={() => submit()} accessibilityRole="button" style={styles.sug}>
            <Icon name="arrow-right" size={15} color={a.ink} />
            <T v="small" style={{ color: a.ink, fontWeight: "700" }}>
              {t("m.searchFor", { text: text.trim() })}
            </T>
          </Pressable>
        </Animated.View>
      )}

      <FilterSheet open={filterOpen} onClose={() => setFilterOpen(false)} value={f} onApply={setF} stores={enabled} />
      <SortSheet open={sortOpen} onClose={() => setSortOpen(false)} value={f.sort ?? "relevance"} onPick={(s) => setF({ ...f, sort: s })} mode={mode} />
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 10 },
  field: { flex: 1, flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, borderRadius: radius.md, borderWidth: 1.5 },
  tools: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 16, marginBottom: 10 },
  tool: { flexDirection: "row", alignItems: "center", gap: 6, minHeight: 44, paddingHorizontal: 12, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth, maxWidth: 200 },
  tag: { flexDirection: "row", alignItems: "center", gap: 6, minHeight: 36, paddingHorizontal: 12, borderRadius: 999 },
  sugs: { position: "absolute", left: 16, right: 16, borderRadius: radius.md, paddingVertical: 6, zIndex: 50 },
  sug: { flexDirection: "row", alignItems: "center", gap: 10, minHeight: 48, paddingHorizontal: 14 },
});
