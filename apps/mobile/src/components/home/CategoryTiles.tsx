import { StyleSheet, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import Animated from "react-native-reanimated";
import { api } from "@/api/client";
import type { MsgKey } from "@/i18n/core";
import type { StoreMode } from "@/lib/types";
import { useT } from "@/i18n";
import { haptic } from "@/lib/haptics";
import { radius, shadow, useTheme } from "@/theme";
import { Icon } from "../Icon";
import { ProductImage, SectionTitle, T, Tap } from "../ui";
import { useAsync } from "../Shop";
import { listEnter } from "@/lib/motion";

/**
 * Category-first home (same tiles as the web app): the shopper, mostly women on a phone, sees photo tiles for what
 * she shops most before anything else. Paths are nodes of the unified category tree; each photo is the top-rated
 * product of that category (an empty-query search reads our database and never triggers a live store fetch).
 */
export const HOME_TILES: { path: string; label: MsgKey; icon: string }[] = [
  { path: "fashion/women/clothing/kurtas-kurtis", label: "tile.kurtas", icon: "dress" },
  { path: "fashion/women/clothing/sarees", label: "tile.sarees", icon: "dress" },
  { path: "fashion/women/clothing/dresses", label: "tile.dresses", icon: "dress" },
  { path: "fashion/women/clothing/tops-t-shirts", label: "tile.tops", icon: "dress" },
  { path: "fashion/women/bags", label: "tile.bags", icon: "bag" },
  { path: "fashion/jewellery", label: "tile.jewellery", icon: "gem" },
  { path: "beauty-personal-care/makeup", label: "tile.makeup", icon: "beauty" },
  { path: "beauty-personal-care/skin-care", label: "tile.skincare", icon: "smile" },
  { path: "fashion/women/footwear", label: "tile.footwear", icon: "shoe" },
  { path: "fashion/kids/girls-clothing", label: "tile.kids", icon: "baby" },
  { path: "fashion/watches", label: "tile.watches", icon: "watch" },
  { path: "home-kitchen/home-decor", label: "tile.decor", icon: "frame" },
];

export function CategoryTiles({ mode }: { mode: StoreMode }) {
  const { t } = useT();
  const { c } = useTheme();
  const { width } = useWindowDimensions();
  const cols = width >= 700 ? 6 : 4;
  const gap = 10;
  const size = Math.floor((Math.min(width, 900) - 32 - gap * (cols - 1)) / cols);
  const images = useAsync(
    () =>
      Promise.all(
        HOME_TILES.map((x) =>
          api
            .search({ q: "", store: mode, category: x.path, sort: "rating" })
            .then((r) => r.items.find((p) => p.image)?.image ?? null)
            .catch(() => null),
        ),
      ),
    [mode],
  );

  return (
    <View style={{ marginTop: 14 }}>
      <SectionTitle title={t("home.shopByCategory")} action={t("cat.seeAll")} onAction={() => router.navigate("/categories")} />
      <View style={[styles.grid, { gap, paddingHorizontal: 16 }]}>
        {HOME_TILES.map((x, i) => {
          const img = images.data?.[i];
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
                <View style={[styles.tile, { width: size, height: size, backgroundColor: img ? "#fff" : c.surface2, borderColor: c.line }, shadow(c)]}>
                  {img ? (
                    <ProductImage uri={img} contentFit="cover" style={{ width: size, height: size }} />
                  ) : (
                    <Icon name={x.icon} size={24} color={c.brand} />
                  )}
                </View>
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
  tile: { borderRadius: radius.lg, overflow: "hidden", alignItems: "center", justifyContent: "center", borderWidth: StyleSheet.hairlineWidth },
});
