import { StyleSheet, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import Animated from "react-native-reanimated";
import { api } from "@/api/client";
import type { MsgKey } from "@/i18n/core";
import type { StoreMode } from "@/lib/types";
import { useT } from "@/i18n";
import { haptic } from "@/lib/haptics";
import { shadow, useTheme } from "@/theme";
import { SectionTitle, T, Tap } from "../ui";
import { CatThumb } from "../CatThumb";
import { useCategoryTree } from "../CategoryRows";
import type { CatNode } from "@/lib/categories";
import { useAsync } from "../Shop";
import { listEnter } from "@/lib/motion";

/**
 * Category-first home (same tiles as the web app): the shopper, mostly women on a phone, sees photo tiles for what
 * she shops most before anything else. Paths are nodes of the unified category tree; each photo is the top-rated
 * product of that category (an empty-query search reads our database and never triggers a live store fetch).
 */
export const HOME_TILES: { path: string; label: MsgKey; icon: string }[] = [
  { path: "fashion/women/clothing/kurtas-kurtis", label: "tile.kurtas", icon: "kurta" },
  { path: "fashion/women/clothing/sarees", label: "tile.sarees", icon: "saree" },
  { path: "fashion/women/clothing/dresses", label: "tile.dresses", icon: "dress" },
  { path: "fashion/women/clothing/tops-t-shirts", label: "tile.tops", icon: "top" },
  { path: "fashion/women/bags", label: "tile.bags", icon: "handbag" },
  { path: "fashion/jewellery", label: "tile.jewellery", icon: "necklace" },
  { path: "beauty-personal-care/makeup", label: "tile.makeup", icon: "lipstick" },
  { path: "beauty-personal-care/skin-care", label: "tile.skincare", icon: "jar" },
  { path: "fashion/women/footwear", label: "tile.footwear", icon: "heels" },
  { path: "fashion/kids/girls-clothing", label: "tile.kids", icon: "romper" },
  { path: "fashion/watches", label: "tile.watches", icon: "watch" },
  { path: "home-kitchen/home-decor", label: "tile.decor", icon: "vase" },
];

function findNode(nodes: CatNode[], path: string): CatNode | null {
  for (const n of nodes) {
    if (n.path === path) return n;
    if (path.startsWith(n.path + "/")) return findNode(n.children, path);
  }
  return null;
}

export function CategoryTiles({ mode }: { mode: StoreMode }) {
  const { t } = useT();
  const { c } = useTheme();
  const { width } = useWindowDimensions();
  const cols = width >= 700 ? 6 : 4;
  const gap = 10;
  const size = Math.floor((Math.min(width, 900) - 32 - gap * (cols - 1)) / cols);
  // Cover photos come with the category tree (one request, cached); only tiles without one search our database.
  const tree = useCategoryTree(mode);
  const covers = HOME_TILES.map((x) => {
    const n = tree ? findNode(tree, x.path) : null;
    return n?.images?.[0] ?? n?.image ?? null;
  });
  const missing = tree ? HOME_TILES.filter((_, i) => !covers[i]).map((x) => x.path) : [];
  const fallback = useAsync(
    () =>
      Promise.all(
        missing.map((path) =>
          api
            .search({ q: "", store: mode, category: path, sort: "rating" })
            .then((r) => [path, r.items.find((p) => p.image)?.image ?? null] as const)
            .catch(() => [path, null] as const),
        ),
      ).then((pairs) => Object.fromEntries(pairs) as Record<string, string | null>),
    [mode, missing.join(",")],
  );

  return (
    <View style={{ marginTop: 14 }}>
      <SectionTitle title={t("home.shopByCategory")} action={t("cat.seeAll")} onAction={() => router.navigate("/categories")} />
      <View style={[styles.grid, { gap, paddingHorizontal: 16 }]}>
        {HOME_TILES.map((x, i) => {
          const img = covers[i] ?? fallback.data?.[x.path] ?? null;
          return (
            <Animated.View key={x.path} entering={listEnter(i)} style={{ width: size }}>
              <Tap
                onPress={() => {
                  haptic.tap();
                  router.push({ pathname: "/category/[...path]", params: { path: x.path.split("/"), store: mode } });
                }}
                accessibilityRole="link"
                accessibilityLabel={t(x.label)}
                style={{ alignItems: "center", gap: 6 }}
              >
                <CatThumb node={{ icon: x.icon, image: img }} mode={mode} size={size} style={shadow(c)} />
                <T v="tiny" numberOfLines={2} style={{ fontWeight: "700", textAlign: "center" }}>
                  {t(x.label)}
                </T>
              </Tap>
            </Animated.View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap" },
});
