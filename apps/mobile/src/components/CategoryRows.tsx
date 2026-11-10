import { useEffect, useState } from "react";
import { StyleSheet, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import Animated from "react-native-reanimated";
import { api } from "@/api/client";
import type { CatNode } from "@/lib/categories";
import type { StoreMode } from "@/lib/types";
import { useTheme } from "@/theme";
import { T, Tap } from "./ui";
import { CatThumb } from "./CatThumb";
import { Icon } from "./Icon";
import { useT } from "@/i18n";
import { listEnter } from "@/lib/motion";

/** Category tree for a store (cached per store for the session). */
const cache = new Map<StoreMode, CatNode[]>();
export function useCategoryTree(store: StoreMode) {
  const [tree, setTree] = useState<CatNode[] | null>(cache.get(store) ?? null);
  useEffect(() => {
    let live = true;
    api
      .categories(store)
      .then((t) => {
        cache.set(store, t);
        if (live) setTree(t);
      })
      .catch(() => live && setTree((cur) => cur ?? []));
    return () => {
      live = false;
    };
  }, [store]);
  return tree ?? cache.get(store) ?? null;
}

export function openCategory(path: string, store: StoreMode) {
  router.push({ pathname: "/category/[...path]", params: { path: path.split("/"), store } });
}

/** Drill-down rows: photo (or icon), English + Bangla name, product count, chevron. */
export function CategoryRows({ nodes, store }: { nodes: CatNode[]; store: StoreMode }) {
  const { c } = useTheme();
  const { t, lang, digits } = useT();
  const bn = lang === "bn";
  return (
    <View style={[styles.list, { backgroundColor: c.surface }]}>
      {nodes.map((n, i) => (
        <Animated.View key={n.path} entering={listEnter(i)}>
          <Tap
            onPress={() => openCategory(n.path, store)}
            accessibilityLabel={`${bn && n.nameBn ? n.nameBn : n.name}${n.count ? `, ${t("m.products", { count: n.count })}` : ""}`}
            style={[styles.row, i > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: c.line }]}
            scaleTo={0.985}
          >
            <CatThumb node={n} mode={store} size={48} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <T v="body" style={{ fontWeight: "700" }} numberOfLines={1}>
                {bn && n.nameBn ? n.nameBn : n.name}
              </T>
              {!!n.nameBn && (
                <T v="tiny" tone="ink3" numberOfLines={1}>
                  {bn ? n.name : n.nameBn}
                </T>
              )}
            </View>
            {!!n.count && (
              <T v="tiny" tone="ink3">
                {digits(n.count)}
              </T>
            )}
            <Icon name={n.children.length ? "chevron-right" : "arrow-right"} size={18} color={c.ink3} />
          </Tap>
        </Animated.View>
      ))}
    </View>
  );
}

/**
 * Sub-categories as photo tiles, 4 to a row (MoveOn / Myntra style): a picture says "saree" faster than a word.
 * Each tile is a real product photo of that category, or its own icon while it has no products.
 */
export function CategoryGrid({ nodes, store }: { nodes: CatNode[]; store: StoreMode }) {
  const { lang } = useT();
  const { width } = useWindowDimensions();
  const cols = width >= 700 ? 6 : 4;
  const gap = 10;
  const size = Math.floor((Math.min(width, 900) - 32 - gap * (cols - 1)) / cols);
  return (
    <View style={[styles.grid, { gap }]}>
      {nodes.map((n, i) => {
        const name = lang === "bn" && n.nameBn ? n.nameBn : n.name;
        return (
          <Animated.View key={n.path} entering={listEnter(i)} style={{ width: size }}>
            <Tap onPress={() => openCategory(n.path, store)} accessibilityRole="link" accessibilityLabel={name} style={{ alignItems: "center", gap: 6 }}>
              <CatThumb node={n} mode={store} size={size} />
              <T v="tiny" numberOfLines={2} style={{ fontWeight: "700", textAlign: "center" }}>
                {name}
              </T>
            </Tap>
          </Animated.View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap" },
  list: { borderRadius: 20, overflow: "hidden" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 56, paddingHorizontal: 14, paddingVertical: 8 },
});
