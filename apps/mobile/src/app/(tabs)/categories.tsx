import { ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import Animated from "react-native-reanimated";
import { useStore } from "@/state/store";
import { STORES , storeText } from "@/lib/markets";
import { STORE_TEXT, radius, useTheme, withAlpha } from "@/theme";
import { Card, Skeleton, T, Tap } from "@/components/ui";
import { CatThumb } from "@/components/CatThumb";
import { CategoryRows, useCategoryTree } from "@/components/CategoryRows";
import { Icon } from "@/components/Icon";
import type { MarketId } from "@/lib/types";
import { useT } from "@/i18n";
import { listEnter } from "@/lib/motion";
import { StoreName } from "@/components/StoreName";

export default function Categories() {
  const { t } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { enabled, mode: homeMode } = useStore();
  const tree = useCategoryTree(homeMode);
  const markets = enabled.filter((m): m is MarketId => m !== "all");

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 32, gap: 18 }}>
      <T v="h1" accessibilityRole="header">{t("nav.categories")}</T>

      <View>
        <T v="label" tone="ink3" style={{ marginBottom: 8 }}>
          {t("m.browseDept")}
          {homeMode !== "all" ? ` · ${STORES[homeMode].name}` : ""}
        </T>
        {tree ? <CategoryRows nodes={tree} store={homeMode} /> : <Skeleton h={320} r={20} />}
      </View>

      <View>
        <T v="label" tone="ink3" style={{ marginBottom: 8 }}>{t("m.everyStore")}</T>
        <View style={styles.grid}>
          {storeText("all", t).categories.map((cat, i) => (
            <Animated.View key={cat.slug} entering={listEnter(i)} style={{ width: "48%" }}>
              <Tap onPress={() => router.push({ pathname: "/search", params: { store: "all", category: cat.slug } })} accessibilityLabel={t("m.catFromEvery", { name: cat.label })}>
                <Card pad={14} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                  <CatThumb node={{ icon: cat.icon }} size={40} />
                  <T v="h3" style={{ fontSize: 15, flex: 1 }} numberOfLines={1}>
                    {cat.label}
                  </T>
                </Card>
              </Tap>
            </Animated.View>
          ))}
        </View>
      </View>

      {markets.map((m) => {
        const s = storeText(m, t);
        const a = STORE_TEXT[m][c.dark ? "dark" : "light"];
        return (
          <View key={m} style={{ gap: 8 }}>
            <Tap onPress={() => router.push(`/store/${m}`)} accessibilityLabel={t("m.openStore", { name: s.name, source: s.source })}>
              <View style={[styles.banner, { backgroundColor: withAlpha(a.ring, c.dark ? 0.12 : 0.07), borderColor: withAlpha(a.ring, 0.35) }]}>
                <View style={{ flex: 1 }}>
                  <StoreName mode={m} size={20} variant="plain" style={{ alignSelf: "flex-start" }} />
                  <T v="tiny" tone="ink2">
                    {t("m.fromSource", { source: s.source })}
                  </T>
                </View>
                <Icon name="chevron-right" size={20} color={c.ink3} />
              </View>
            </Tap>
            <View style={styles.grid}>
              {s.categories.map((cat) => (
                <Tap
                  key={cat.slug + cat.label}
                  style={{ width: "48%" }}
                  onPress={() => router.push({ pathname: "/search", params: { store: m, category: cat.slug } })}
                  accessibilityLabel={t("m.catIn", { cat: cat.label, store: s.name })}
                >
                  <Card pad={12} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                    <CatThumb node={{ icon: cat.icon }} mode={m} size={36} />
                    <T v="small" style={{ fontWeight: "700", flex: 1 }} numberOfLines={2}>
                      {cat.label}
                    </T>
                  </Card>
                </Tap>
              ))}
            </View>
          </View>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap", justifyContent: "space-between", rowGap: 10 },
  banner: { flexDirection: "row", alignItems: "center", gap: 12, paddingHorizontal: 16, paddingVertical: 12, borderRadius: radius.md, borderWidth: 1, minHeight: 60 },
});
