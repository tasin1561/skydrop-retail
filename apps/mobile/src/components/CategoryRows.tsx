import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import Animated from "react-native-reanimated";
import { api } from "@/api/client";
import type { CatNode } from "@/lib/categories";
import type { StoreMode } from "@/lib/types";
import { useTheme } from "@/theme";
import { IconChip, T, Tap } from "./ui";
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

/** Drill-down rows: icon, English + Bangla name, product count, chevron. */
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
            <IconChip icon={n.icon} mode={store} size={36} />
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

const styles = StyleSheet.create({
  list: { borderRadius: 20, overflow: "hidden" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 56, paddingHorizontal: 14, paddingVertical: 8 },
});
