import { useMemo, useState } from "react";
import { ScrollView, StyleSheet, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { findTrail } from "@/lib/categories";
import { isStoreMode, STORES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { useAccent, useTheme } from "@/theme";
import { IconButton, Skeleton, T, Tap } from "@/components/ui";
import { ProductGrid } from "@/components/ProductGrid";
import { CategoryGrid, openCategory, useCategoryTree } from "@/components/CategoryRows";
import { CatThumb } from "@/components/CatThumb";
import { useT } from "@/i18n";
import { Icon } from "@/components/Icon";
import { BrandSheet, MAX_BRANDS, toggleBrand } from "@/components/Filters";
import type { BrandFacet } from "@/api/types";

/** Category drill-down: breadcrumbs, child categories, then the products of the whole subtree (search ?category=). */
export default function CategoryScreen() {
  const { t, lang } = useT();
  const params = useLocalSearchParams<{ path: string | string[]; store?: string; brand?: string }>();
  const { mode } = useStore();
  const store = isStoreMode(params.store) ? params.store : mode;
  const path = (Array.isArray(params.path) ? params.path : String(params.path ?? "").split("/")).filter(Boolean).join("/");
  const { c } = useTheme();
  const a = useAccent(store);
  const insets = useSafeAreaInsets();
  const tree = useCategoryTree(store);
  const trail = useMemo(() => (tree ? findTrail(tree, path) : []), [tree, path]);
  const node = trail[trail.length - 1];
  const [brand, setBrand] = useState<string[] | undefined>(() =>
    params.brand ? params.brand.split(",").map((b) => b.trim()).filter(Boolean).slice(0, MAX_BRANDS) : undefined,
  );
  const [brands, setBrands] = useState<BrandFacet[]>([]);
  const [brandOpen, setBrandOpen] = useState(false);
  const searchParams = useMemo(() => ({ store, category: path, brand }), [store, path, brand]);
  const nm = (n: { name: string; nameBn?: string | null }) => (lang === "bn" && n.nameBn ? n.nameBn : n.name);

  const header = (
    <View style={{ gap: 12, paddingBottom: 12 }}>
      <View style={styles.crumbs}>
        <Tap onPress={() => router.navigate("/categories")} accessibilityLabel={t("cat.allCategories")} style={[styles.crumb, { backgroundColor: c.surface2 }]}>
          <T v="tiny" style={{ fontWeight: "700" }}>
            {store === "all" ? t("common.all") : STORES[store].name}
          </T>
        </Tap>
        {trail.slice(0, -1).map((n) => (
          <Tap key={n.path} onPress={() => openCategory(n.path, store)} accessibilityLabel={nm(n)} style={[styles.crumb, { backgroundColor: c.surface2 }]}>
            <T v="tiny" style={{ fontWeight: "700" }} numberOfLines={1}>
              {nm(n)}
            </T>
          </Tap>
        ))}
      </View>
      {node ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
          <CatThumb node={node} mode={store} size={56} />
          <View style={{ flex: 1 }}>
            <T v="h1" accessibilityRole="header" numberOfLines={2}>
              {nm(node)}
            </T>
            {!!node.nameBn && (
              <T v="small" tone="ink3">
                {lang === "bn" ? node.name : node.nameBn}
              </T>
            )}
          </View>
        </View>
      ) : tree ? (
        <T v="h2">{t("c.notFound")}</T>
      ) : (
        <Skeleton h={48} />
      )}
      {!!node?.children.length && <CategoryGrid nodes={node.children} store={store} />}
      {node && (
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginTop: 4 }}>
          <T v="label" tone="ink3" style={{ flex: 1 }}>
            {node.children.length ? t("m.everythingIn", { name: nm(node) }) : t("m.productsH")}
          </T>
          {(brands.length > 0 || !!brand?.length) && (
            <Tap
              onPress={() => setBrandOpen(true)}
              accessibilityLabel={brand?.length ? t("mx.brandN", { count: brand.length }) : t("mx.brand")}
              style={[styles.tool, { backgroundColor: brand?.length ? a.soft : c.surface, borderColor: brand?.length ? a.accent : c.line }]}
            >
              <T v="small" style={{ fontWeight: "700", color: brand?.length ? a.ink : c.ink }}>
                {brand?.length ? t("mx.brandN", { count: brand.length }) : t("mx.brand")}
              </T>
              <Icon name="chevron-down" size={14} color={brand?.length ? a.ink : c.ink} />
            </Tap>
          )}
        </View>
      )}
      {!!brand?.length && (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
          {brand.map((b) => (
            <Tap key={b} onPress={() => setBrand(toggleBrand(brand, b))} accessibilityLabel={t("mx.removeBrand", { name: b })} style={[styles.tag, { backgroundColor: a.soft }]}>
              <T v="small" style={{ color: a.ink, fontWeight: "700" }} numberOfLines={1}>
                {b}
              </T>
              <Icon name="x" size={14} color={a.ink} />
            </Tap>
          ))}
        </View>
      )}
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 6 }}>
      <View style={styles.top}>
        <IconButton icon="arrow-left" label={t("common.back")} onPress={() => router.back()} bg={c.surface2} />
        <T v="h3" numberOfLines={1} style={{ flex: 1 }}>
          {node ? nm(node) : t("nav.categories")}
        </T>
        <IconButton icon="search" label={t("search.submit")} onPress={() => router.push({ pathname: "/search", params: { store, category: path, focus: "1" } })} bg={a.soft} tint={a.ink} />
      </View>
      {node ? (
        <ProductGrid params={searchParams} header={header} onBrands={setBrands} />
      ) : (
        <ScrollView contentContainerStyle={{ paddingHorizontal: 16 }}>{header}</ScrollView>
      )}
      <BrandSheet open={brandOpen} onClose={() => setBrandOpen(false)} value={brand} onApply={setBrand} brands={brands} mode={store} />
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 6 },
  crumbs: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  tool: { flexDirection: "row", alignItems: "center", gap: 6, minHeight: 44, paddingHorizontal: 12, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth },
  tag: { flexDirection: "row", alignItems: "center", gap: 6, minHeight: 44, paddingHorizontal: 12, borderRadius: 999, maxWidth: 220 },
  crumb: { minHeight: 32, paddingHorizontal: 12, borderRadius: 999, justifyContent: "center", maxWidth: 200 },
});
