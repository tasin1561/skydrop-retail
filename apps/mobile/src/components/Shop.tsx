// Home / store building blocks: hero, category chips, product rail, how-it-works.
import { useEffect, useState } from "react";
import { FlatList, Pressable, StyleSheet, View } from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import Animated, { FadeIn, FadeInDown, useAnimatedStyle, useSharedValue, withRepeat, withSequence, withTiming, Easing } from "react-native-reanimated";
import { router } from "expo-router";
import type { Product, StoreMode } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { ACCENTS, radius, useAccent, useTheme } from "@/theme";
import { Icon } from "./Icon";
import { ProductCard, ProductCardSkeleton } from "./ProductCard";
import { Card, IconChip, SectionTitle, T, Tap } from "./ui";
import { haptic } from "@/lib/haptics";

export function StoreHero({ mode }: { mode: StoreMode }) {
  const s = STORES[mode];
  const a = ACCENTS[mode];
  const float = useSharedValue(0);
  useEffect(() => {
    float.value = withRepeat(withSequence(withTiming(-6, { duration: 1600, easing: Easing.inOut(Easing.sin) }), withTiming(0, { duration: 1600, easing: Easing.inOut(Easing.sin) })), -1);
  }, [float]);
  const floatStyle = useAnimatedStyle(() => ({ transform: [{ translateY: float.value }] }));

  return (
    <Animated.View key={mode} entering={FadeIn.duration(260)} style={{ marginHorizontal: 16 }}>
      <LinearGradient colors={[a.heroA, a.heroB]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <View style={[styles.blob, { right: -30, top: -30, width: 140, height: 140 }]} />
        <View style={[styles.blob, { left: -40, bottom: -50, width: 120, height: 120 }]} />
        <View style={{ flex: 1, gap: 8 }}>
          <View style={styles.heroTag}>
            <Icon name={s.icon} size={12} color="#fff" />
            <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
              {mode === "all" ? "ALL STORES" : `${s.name.toUpperCase()} · ${s.source}`}
            </T>
          </View>
          <T v="h2" tone="white" style={{ fontSize: 21, lineHeight: 26 }}>
            {s.heroTitle}
          </T>
          <T v="small" style={{ color: "rgba(255,255,255,0.88)" }}>
            {s.heroText}
          </T>
          <Tap
            onPress={() => router.push({ pathname: "/search", params: { store: mode } })}
            accessibilityLabel={`Search ${s.name}`}
            style={styles.heroBtn}
          >
            <Icon name="search" size={16} color={a.accent} />
            <T v="small" style={{ color: a.accent, fontWeight: "800" }}>
              Search {mode === "all" ? "all stores" : s.name}
            </T>
          </Tap>
        </View>
        <Animated.View style={[styles.heroArt, floatStyle]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Icon name="package" size={44} color="#fff" />
        </Animated.View>
      </LinearGradient>
    </Animated.View>
  );
}

export function CategoryChips({ mode, onPick }: { mode: StoreMode; onPick?: (slug: string) => void }) {
  const { c } = useTheme();
  const a = useAccent(mode);
  return (
    <FlatList
      horizontal
      data={STORES[mode].categories}
      keyExtractor={(x) => x.slug + x.label}
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{ paddingHorizontal: 16, gap: 8 }}
      renderItem={({ item, index }) => (
        <Animated.View entering={FadeInDown.delay(index * 40).springify().damping(18)}>
          <Tap
            onPress={() => {
              haptic.tap();
              if (onPick) onPick(item.slug);
              else router.push({ pathname: "/search", params: { store: mode, category: item.slug } });
            }}
            accessibilityLabel={`${item.label} in ${STORES[mode].name}`}
            style={[styles.chip, { backgroundColor: c.surface, borderColor: c.line }]}
          >
            <View style={[styles.chipIcon, { backgroundColor: a.soft }]}>
              <Icon name={item.icon} size={15} color={a.ink} />
            </View>
            <T v="small" style={{ fontWeight: "700" }}>
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
  const W = 156;
  if (!loading && !items.length) return null;
  return (
    <View style={{ marginTop: 24 }}>
      <SectionTitle title={title} action={onMore ? "See all" : undefined} onAction={onMore} />
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

const STEPS = [
  { icon: "search", title: "Pick from Indian stores", text: "Search amazon.in, flipkart.com and bigbasket.com products, priced in taka." },
  { icon: "shield", title: "We verify and buy", text: "Live price and stock check at checkout, then we buy it in India for you." },
  { icon: "package", title: "Skydrop hub in Kolkata", text: "Your parcel is received, checked and packed for the border." },
  { icon: "truck", title: "Delivered to your door", text: "Cleared and delivered anywhere in Bangladesh in 7-12 days." },
];

export function HowItWorks({ mode }: { mode: StoreMode }) {
  const { c } = useTheme();
  const a = useAccent(mode);
  return (
    <View style={{ marginTop: 28, paddingHorizontal: 16 }}>
      <T v="h2" accessibilityRole="header" style={{ marginBottom: 10 }}>
        How it works
      </T>
      <Card pad={14} style={{ gap: 2 }}>
        {STEPS.map((s, i) => (
          <View key={s.title} style={{ flexDirection: "row", gap: 12 }}>
            <View style={{ alignItems: "center" }}>
              <IconChip icon={s.icon} mode={mode} size={38} />
              {i < STEPS.length - 1 && <View style={{ width: 2, flex: 1, minHeight: 14, backgroundColor: a.soft, marginVertical: 4 }} />}
            </View>
            <View style={{ flex: 1, paddingBottom: 12 }}>
              <T v="h3" style={{ fontSize: 15 }}>
                {i + 1}. {s.title}
              </T>
              <T v="small" tone="ink2">
                {s.text}
              </T>
            </View>
          </View>
        ))}
        <T v="tiny" style={{ color: c.ink3 }}>
          Products are sold by the Indian stores named on each item. Skydrop Retail buys on your behalf and is not affiliated with them.
        </T>
      </Card>
    </View>
  );
}

export function SearchBar({ mode, placeholder }: { mode: StoreMode; placeholder?: string }) {
  const { c } = useTheme();
  return (
    <Pressable
      onPress={() => router.push({ pathname: "/search", params: { store: mode, focus: "1" } })}
      accessibilityRole="search"
      accessibilityLabel="Search products"
      style={[styles.search, { backgroundColor: c.surface, borderColor: c.line }]}
    >
      <Icon name="search" size={18} color={c.ink3} />
      <T v="body" tone="ink3" numberOfLines={1} style={{ flex: 1 }}>
        {placeholder ?? `Search ${mode === "all" ? "every Indian store" : STORES[mode].source}`}
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
  hero: { borderRadius: radius.lg + 4, padding: 18, flexDirection: "row", overflow: "hidden", minHeight: 190 },
  blob: { position: "absolute", borderRadius: 999, backgroundColor: "rgba(255,255,255,0.12)" },
  heroTag: { flexDirection: "row", alignItems: "center", gap: 5, alignSelf: "flex-start", backgroundColor: "rgba(255,255,255,0.18)", paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999 },
  heroBtn: { marginTop: 6, flexDirection: "row", alignItems: "center", gap: 8, alignSelf: "flex-start", backgroundColor: "#fff", paddingHorizontal: 14, minHeight: 44, borderRadius: 999 },
  heroArt: { width: 76, height: 76, borderRadius: 24, backgroundColor: "rgba(255,255,255,0.18)", alignItems: "center", justifyContent: "center", alignSelf: "center", marginLeft: 8 },
  chip: { flexDirection: "row", alignItems: "center", gap: 8, paddingLeft: 6, paddingRight: 14, minHeight: 48, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth },
  chipIcon: { width: 34, height: 34, borderRadius: 17, alignItems: "center", justifyContent: "center" },
  search: { flexDirection: "row", alignItems: "center", gap: 10, marginHorizontal: 16, paddingHorizontal: 14, minHeight: 50, borderRadius: radius.md, borderWidth: StyleSheet.hairlineWidth },
});
