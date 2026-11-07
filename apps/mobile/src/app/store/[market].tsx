import { useState } from "react";
import { StyleSheet, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { isMarket, STORES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { ACCENTS, useTheme } from "@/theme";
import { CategoryChips, SearchBar, StoreHero } from "@/components/Shop";
import { ProductGrid } from "@/components/ProductGrid";
import { Empty, IconButton, T } from "@/components/ui";

export default function StoreScreen() {
  const { market } = useLocalSearchParams<{ market: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { enabled, totals } = useStore();
  const [category, setCategory] = useState<string | undefined>();

  if (!isMarket(market) || !enabled.includes(market))
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 40 }}>
        <Empty icon="slash" title="Store not available" text="This store is switched off right now. Try another store." action="Back" onAction={() => router.back()} />
      </View>
    );

  const s = STORES[market];
  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 6 }}>
      <View style={styles.top}>
        <IconButton icon="arrow-left" label="Back" onPress={() => router.back()} bg={c.surface2} />
        <View style={{ flex: 1 }}>
          <T v="h2" accessibilityRole="header" style={{ color: c.dark ? ACCENTS[market].accent2 : ACCENTS[market].accent }}>
            {s.name}
          </T>
          <T v="tiny" tone="ink2">
            Products from {s.source}
          </T>
        </View>
        <IconButton icon="shopping-cart" label={`Cart, ${totals.count} items`} badge={totals.count} onPress={() => router.navigate("/cart")} />
      </View>
      <ProductGrid
        params={{ store: market, category, sort: "relevance" }}
        header={
          <View style={{ marginHorizontal: -16, gap: 14, marginBottom: 6 }}>
            <SearchBar mode={market} />
            <StoreHero mode={market} />
            <CategoryChips mode={market} onPick={(slug) => setCategory((cur) => (cur === slug ? undefined : slug))} />
            <T v="h2" style={{ paddingHorizontal: 16, marginTop: 4 }}>
              {category ? s.categories.find((x) => x.slug === category)?.label : `Popular on ${s.name}`}
            </T>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 10 },
});
