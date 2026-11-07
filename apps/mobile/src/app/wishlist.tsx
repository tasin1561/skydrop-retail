import { FlatList, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { useAsync } from "@/components/Shop";
import { ProductCard, ProductCardSkeleton } from "@/components/ProductCard";
import { Empty } from "@/components/ui";

export default function Wishlist() {
  const { wish } = useStore();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const W = (width - 16 * 2 - 12) / 2;
  const key = wish.join(",");
  const { data, loading } = useAsync(() => api.products(wish), [key]);
  const items = (data ?? []).filter((p) => wish.includes(p.id));

  if (!wish.length) return <Empty icon="heart" title="Nothing saved yet" text="Tap the heart on any product to keep it here." action="Browse products" onAction={() => router.navigate("/")} />;

  return (
    <FlatList
      data={loading && !data ? [] : items}
      numColumns={2}
      keyExtractor={(p) => p.id}
      columnWrapperStyle={{ gap: 12 }}
      contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: insets.bottom + 24 }}
      renderItem={({ item }) => <ProductCard p={item} width={W} />}
      ListEmptyComponent={
        <View style={{ flexDirection: "row", gap: 12 }}>
          <ProductCardSkeleton width={W} />
          <ProductCardSkeleton width={W} />
        </View>
      }
    />
  );
}
