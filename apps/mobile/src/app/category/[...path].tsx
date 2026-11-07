import { useMemo } from "react";
import { ScrollView, StyleSheet, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { findTrail } from "@/lib/categories";
import { isStoreMode, STORES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { useAccent, useTheme } from "@/theme";
import { IconButton, IconChip, Skeleton, T, Tap } from "@/components/ui";
import { ProductGrid } from "@/components/ProductGrid";
import { CategoryRows, openCategory, useCategoryTree } from "@/components/CategoryRows";

/** Category drill-down: breadcrumbs, child categories, then the products of the whole subtree (search ?category=). */
export default function CategoryScreen() {
  const params = useLocalSearchParams<{ path: string | string[]; store?: string }>();
  const { mode } = useStore();
  const store = isStoreMode(params.store) ? params.store : mode;
  const path = (Array.isArray(params.path) ? params.path : String(params.path ?? "").split("/")).filter(Boolean).join("/");
  const { c } = useTheme();
  const a = useAccent(store);
  const insets = useSafeAreaInsets();
  const tree = useCategoryTree(store);
  const trail = useMemo(() => (tree ? findTrail(tree, path) : []), [tree, path]);
  const node = trail[trail.length - 1];
  const searchParams = useMemo(() => ({ store, category: path }), [store, path]);

  const header = (
    <View style={{ gap: 12, paddingBottom: 12 }}>
      <View style={styles.crumbs}>
        <Tap onPress={() => router.navigate("/categories")} accessibilityLabel="All categories" style={[styles.crumb, { backgroundColor: c.surface2 }]}>
          <T v="tiny" style={{ fontWeight: "700" }}>
            {store === "all" ? "All" : STORES[store].name}
          </T>
        </Tap>
        {trail.slice(0, -1).map((n) => (
          <Tap key={n.path} onPress={() => openCategory(n.path, store)} accessibilityLabel={n.name} style={[styles.crumb, { backgroundColor: c.surface2 }]}>
            <T v="tiny" style={{ fontWeight: "700" }} numberOfLines={1}>
              {n.name}
            </T>
          </Tap>
        ))}
      </View>
      {node ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
          <IconChip icon={node.icon} mode={store} size={48} />
          <View style={{ flex: 1 }}>
            <T v="h1" accessibilityRole="header" numberOfLines={2}>
              {node.name}
            </T>
            {!!node.nameBn && (
              <T v="small" tone="ink3">
                {node.nameBn}
              </T>
            )}
          </View>
        </View>
      ) : tree ? (
        <T v="h2">Category not found</T>
      ) : (
        <Skeleton h={48} />
      )}
      {!!node?.children.length && <CategoryRows nodes={node.children} store={store} />}
      {node && (
        <T v="label" tone="ink3" style={{ marginTop: 4 }}>
          {node.children.length ? `Everything in ${node.name}` : "Products"}
        </T>
      )}
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 6 }}>
      <View style={styles.top}>
        <IconButton icon="arrow-left" label="Back" onPress={() => router.back()} bg={c.surface2} />
        <T v="h3" numberOfLines={1} style={{ flex: 1 }}>
          {node?.name ?? "Categories"}
        </T>
        <IconButton icon="search" label="Search" onPress={() => router.push({ pathname: "/search", params: { store, category: path, focus: "1" } })} bg={a.soft} tint={a.ink} />
      </View>
      {node ? (
        <ProductGrid params={searchParams} header={header} />
      ) : (
        <ScrollView contentContainerStyle={{ paddingHorizontal: 16 }}>{header}</ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 6 },
  crumbs: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  crumb: { minHeight: 32, paddingHorizontal: 12, borderRadius: 999, justifyContent: "center", maxWidth: 200 },
});
