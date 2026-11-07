import { useCallback, useState } from "react";
import { FlatList, RefreshControl, StyleSheet, View, useWindowDimensions } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { LinearGradient } from "expo-linear-gradient";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { STORES, isStoreMode } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { ACCENTS, radius, useTheme } from "@/theme";
import { useAsync } from "@/components/Shop";
import { StoreSwitcher } from "@/components/StoreSwitcher";
import { ProductCard, ProductCardSkeleton } from "@/components/ProductCard";
import { Empty, IconButton, T } from "@/components/ui";
import { Icon } from "@/components/Icon";

export default function Deals() {
  const params = useLocalSearchParams<{ store?: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { enabled, mode: homeMode } = useStore();
  const [store, setStore] = useState<StoreMode>(isStoreMode(params.store) ? params.store : homeMode);
  const [refreshing, setRefreshing] = useState(false);
  const feed = useAsync(() => api.offers(store, 40), [store]);
  const W = (width - 32 - 12) / 2;
  const a = ACCENTS[store];

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    feed.reload();
    setTimeout(() => setRefreshing(false), 600);
  }, [feed]);

  const withOffers = (feed.data ?? []).filter((p) => (p.offers?.length ?? 0) > 0).length;

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 6 }}>
      <View style={styles.top}>
        <IconButton icon="arrow-left" label="Back" onPress={() => router.back()} bg={c.surface2} />
        <T v="h2" accessibilityRole="header" style={{ flex: 1 }}>
          Live deals and offers
        </T>
      </View>
      <View style={{ paddingHorizontal: 16, marginBottom: 10 }}>
        <StoreSwitcher value={store} onChange={setStore} modes={enabled} compact />
      </View>
      <FlatList
        data={feed.loading ? [] : (feed.data ?? [])}
        numColumns={2}
        keyExtractor={(p) => p.id}
        columnWrapperStyle={{ gap: 12 }}
        contentContainerStyle={{ paddingHorizontal: 16, gap: 12, paddingBottom: insets.bottom + 32 }}
        renderItem={({ item }) => <ProductCard p={item} width={W} />}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.brand} colors={[c.brand]} />}
        ListHeaderComponent={
          <LinearGradient colors={[a.heroA, a.heroB]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
            <Icon name="zap" size={22} color="#fff" />
            <View style={{ flex: 1 }}>
              <T v="h3" tone="white">
                {store === "all" ? "Every connected store" : `${STORES[store].name} · ${STORES[store].source}`}
              </T>
              <T v="tiny" style={{ color: "rgba(255,255,255,0.9)" }}>
                {feed.loading ? "Loading offers…" : `${withOffers} with store offers, plus the biggest discounts. Coupons we can use are already in your price.`}
              </T>
            </View>
          </LinearGradient>
        }
        ListEmptyComponent={
          feed.loading ? (
            <View style={{ gap: 12 }}>
              {[0, 1].map((r) => (
                <View key={r} style={{ flexDirection: "row", gap: 12 }}>
                  <ProductCardSkeleton width={W} />
                  <ProductCardSkeleton width={W} />
                </View>
              ))}
            </View>
          ) : feed.error ? (
            <Empty icon="wifi-off" title="Couldn't load deals" text={feed.error} action="Try again" onAction={feed.reload} />
          ) : (
            <Empty icon="tag" title="No live deals right now" text="Check back soon, offers change through the day." />
          )
        }
        initialNumToRender={6}
        windowSize={7}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 10 },
  hero: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderRadius: radius.lg, marginBottom: 4 },
});
