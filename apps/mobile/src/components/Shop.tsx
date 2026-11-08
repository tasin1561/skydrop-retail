// Home / store building blocks: hero, category chips, product rail, how-it-works.
import { useEffect, useState } from "react";
import { FlatList, Pressable, StyleSheet, View } from "react-native";
import Animated from "react-native-reanimated";
import { router } from "expo-router";
import type { Product, StoreMode } from "@/lib/types";
import { STORES , storeText } from "@/lib/markets";
import { radius, useTheme } from "@/theme";
import { Icon } from "./Icon";
import { ProductCard, ProductCardSkeleton } from "./ProductCard";
import { Card, SectionTitle, T, Tap } from "./ui";
import { haptic } from "@/lib/haptics";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";
import { enterFade, listEnter } from "@/lib/motion";
import { StoreName } from "./StoreName";

/** Calm, light hero card: eyebrow, headline, one line of value, a solid ink search action. No gradient. */
export function StoreHero({ mode }: { mode: StoreMode }) {
  const { t } = useT();
  const { c } = useTheme();
  const s = storeText(mode, t);

  return (
    <Animated.View key={mode} entering={enterFade} style={{ marginHorizontal: 16 }}>
      <View style={[styles.hero, { backgroundColor: c.surface, borderColor: c.line }]}>
        <View style={{ gap: 8 }}>
          {mode === "all" ? (
            <T v="tiny" tone="ink3">
              Skydrop Retail
            </T>
          ) : (
            <StoreName mode={mode} size={13} source />
          )}
          <T v="h2" style={{ fontSize: 22, lineHeight: 28, letterSpacing: -0.3 }}>
            {s.heroTitle}
          </T>
          <T v="small" tone="ink2">
            {s.heroText}
          </T>
          <Tap
            onPress={() => router.push({ pathname: "/search", params: { store: mode } })}
            accessibilityLabel={t("m.searchIn", { name: s.name })}
            style={[styles.heroBtn, { backgroundColor: c.primary }]}
          >
            <Icon name="search" size={16} color={c.onPrimary} />
            <T v="small" style={{ color: c.onPrimary, fontWeight: "500" }}>
              {mode === "all" ? t("m.searchAllStores") : t("m.searchIn", { name: s.name })}
            </T>
          </Tap>
        </View>
      </View>
    </Animated.View>
  );
}

export function CategoryChips({ mode, onPick }: { mode: StoreMode; onPick?: (slug: string) => void }) {
  const { t } = useT();
  const { c } = useTheme();
  return (
    <FlatList
      horizontal
      data={storeText(mode, t).categories}
      keyExtractor={(x) => x.slug + x.label}
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{ paddingHorizontal: 16, gap: 8 }}
      renderItem={({ item, index }) => (
        <Animated.View entering={listEnter(index)}>
          <Tap
            onPress={() => {
              haptic.tap();
              if (onPick) onPick(item.slug);
              else router.push({ pathname: "/search", params: { store: mode, category: item.slug } });
            }}
            accessibilityLabel={t("m.catIn", { cat: item.label, store: STORES[mode].name })}
            style={[styles.chip, { backgroundColor: c.surface, borderColor: c.line }]}
          >
            <Icon name={item.icon} size={16} color={c.ink2} />
            <T v="small" style={{ fontWeight: "500" }}>
              {item.label}
            </T>
          </Tap>
        </Animated.View>
      )}
    />
  );
}

export function Rail({
  title,
  items,
  loading,
  onMore,
}: {
  title: string;
  items: Product[];
  loading?: boolean;
  onMore?: () => void;
}) {
  const { t } = useT();
  const W = 156;
  if (!loading && !items.length) return null;
  return (
    <View style={{ marginTop: 24 }}>
      <SectionTitle title={title} action={onMore ? t("cat.seeAll") : undefined} onAction={onMore} />
      {loading ? (
        <View style={{ flexDirection: "row", gap: 12, paddingHorizontal: 16 }}>
          {[0, 1, 2].map((i) => (
            <ProductCardSkeleton key={i} width={W} />
          ))}
        </View>
      ) : (
        <FlatList
          horizontal
          data={items}
          keyExtractor={(p) => p.id}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ paddingHorizontal: 16, gap: 12, paddingBottom: 8, paddingTop: 2 }}
          renderItem={({ item }) => <ProductCard p={item} width={W} />}
          initialNumToRender={4}
          windowSize={5}
          removeClippedSubviews
        />
      )}
    </View>
  );
}

const STEPS: { icon: string; title: MsgKey; text: MsgKey }[] = [
  { icon: "search", title: "how.s1.title", text: "how.s1.text" },
  { icon: "shield", title: "how.s2.title", text: "how.s2.text" },
  { icon: "package", title: "how.s3.title", text: "how.s3.text" },
  { icon: "truck", title: "how.s4.title", text: "how.s4.text" },
];

export function HowItWorks({ mode }: { mode: StoreMode }) {
  const { t, digits } = useT();
  const { c } = useTheme();
  void mode;
  return (
    <View style={{ marginTop: 28, paddingHorizontal: 16 }}>
      <T v="h2" accessibilityRole="header" style={{ marginBottom: 10 }}>{t("footer.howItWorks")}</T>
      <Card pad={14} style={{ gap: 2 }}>
        {STEPS.map((s, i) => (
          <View key={s.title} style={{ flexDirection: "row", gap: 12 }}>
            <View style={{ alignItems: "center", width: 28 }}>
              <T v="small" tone="ink3" style={{ fontWeight: "500", fontVariant: ["tabular-nums"] }}>
                {digits(String(i + 1).padStart(2, "0"))}
              </T>
              {i < STEPS.length - 1 && <View style={{ width: StyleSheet.hairlineWidth, flex: 1, minHeight: 14, backgroundColor: c.line2, marginVertical: 4 }} />}
            </View>
            <View style={{ flex: 1, paddingBottom: 12 }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                <Icon name={s.icon} size={15} color={c.ink3} />
                <T v="h3" style={{ fontSize: 15 }}>
                  {t(s.title)}
                </T>
              </View>
              <T v="small" tone="ink2">
                {t(s.text)}
              </T>
            </View>
          </View>
        ))}
        <T v="tiny" style={{ color: c.ink3 }}>{t("m.soldBy")}</T>
      </Card>
    </View>
  );
}

export function SearchBar({ mode, placeholder }: { mode: StoreMode; placeholder?: string }) {
  const { t } = useT();
  const { c } = useTheme();
  return (
    <Pressable
      onPress={() => router.push({ pathname: "/search", params: { store: mode, focus: "1" } })}
      accessibilityRole="search"
      accessibilityLabel={t("cat.searchProducts")}
      style={[styles.search, { backgroundColor: c.surface, borderColor: c.line }]}
    >
      <Icon name="search" size={18} color={c.ink3} />
      <T v="body" tone="ink3" numberOfLines={1} style={{ flex: 1 }}>
        {placeholder ?? (mode === "all" ? t("m.searchEvery") : t("m.searchIn", { name: STORES[mode].source }))}
      </T>
    </Pressable>
  );
}

export function useAsync<T>(fn: () => Promise<T>, deps: unknown[]) {
  const [state, setState] = useState<{ data: T | null; loading: boolean; error: string | null }>({ data: null, loading: true, error: null });
  const [tick, setTick] = useState(0);
  const key = JSON.stringify(deps) + ":" + tick;
  const [loadedKey, setLoadedKey] = useState(key);
  if (key !== loadedKey) {
    setLoadedKey(key);
    setState((s) => ({ ...s, loading: true, error: null }));
  }
  useEffect(() => {
    let live = true;
    fn()
      .then((data) => live && setState({ data, loading: false, error: null }))
      .catch((e: Error) => live && setState({ data: null, loading: false, error: e.message }));
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick]);
  return { ...state, reload: () => setTick((t) => t + 1) };
}

const styles = StyleSheet.create({
  hero: { borderRadius: radius.lg, padding: 20, overflow: "hidden", borderWidth: StyleSheet.hairlineWidth },
  heroBtn: { marginTop: 8, flexDirection: "row", alignItems: "center", gap: 8, alignSelf: "flex-start", paddingHorizontal: 16, minHeight: 44, borderRadius: radius.sm },
  chip: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 14, minHeight: 44, borderRadius: radius.sm, borderWidth: StyleSheet.hairlineWidth },
  search: { flexDirection: "row", alignItems: "center", gap: 10, marginHorizontal: 16, paddingHorizontal: 14, minHeight: 50, borderRadius: radius.md, borderWidth: StyleSheet.hairlineWidth },
});
