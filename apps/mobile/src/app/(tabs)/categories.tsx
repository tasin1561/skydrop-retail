import { ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import Animated, { FadeInDown } from "react-native-reanimated";
import { useStore } from "@/state/store";
import { STORES } from "@/lib/markets";
import { ACCENTS, radius, useTheme } from "@/theme";
import { Card, IconChip, Skeleton, T, Tap } from "@/components/ui";
import { CategoryRows, useCategoryTree } from "@/components/CategoryRows";
import { Icon } from "@/components/Icon";
import type { MarketId } from "@/lib/types";

export default function Categories() {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { enabled, mode: homeMode } = useStore();
  const tree = useCategoryTree(homeMode);
  const markets = enabled.filter((m): m is MarketId => m !== "all");

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 32, gap: 18 }}>
      <T v="h1" accessibilityRole="header">
        Categories
      </T>

      <View>
        <T v="label" tone="ink3" style={{ marginBottom: 8 }}>
          Browse by department{homeMode !== "all" ? ` · ${STORES[homeMode].name}` : ""}
        </T>
        {tree ? <CategoryRows nodes={tree} store={homeMode} /> : <Skeleton h={320} r={20} />}
      </View>

      <View>
        <T v="label" tone="ink3" style={{ marginBottom: 8 }}>
          Every store
        </T>
        <View style={styles.grid}>
          {STORES.all.categories.map((cat, i) => (
            <Animated.View key={cat.slug} entering={FadeInDown.delay(i * 40).springify().damping(18)} style={{ width: "48%" }}>
              <Tap onPress={() => router.push({ pathname: "/search", params: { store: "all", category: cat.slug } })} accessibilityLabel={`${cat.label} from every store`}>
                <Card pad={14} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                  <IconChip icon={cat.icon} size={40} />
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
        const s = STORES[m];
        const a = ACCENTS[m];
        return (
          <View key={m} style={{ gap: 8 }}>
            <Tap onPress={() => router.push(`/store/${m}`)} accessibilityLabel={`Open ${s.name}, from ${s.source}`}>
              <LinearGradient colors={[a.heroA, a.heroB]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.banner}>
                <Icon name={s.icon} size={20} color="#fff" />
                <View style={{ flex: 1 }}>
                  <T v="h3" tone="white">
                    {s.name}
                  </T>
                  <T v="tiny" style={{ color: "rgba(255,255,255,0.9)" }}>
                    From {s.source}
                  </T>
                </View>
                <Icon name="chevron-right" size={20} color="#fff" />
              </LinearGradient>
            </Tap>
            <View style={styles.grid}>
              {s.categories.map((cat) => (
                <Tap
                  key={cat.slug + cat.label}
                  style={{ width: "48%" }}
                  onPress={() => router.push({ pathname: "/search", params: { store: m, category: cat.slug } })}
                  accessibilityLabel={`${cat.label} in ${s.name}`}
                >
                  <Card pad={12} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                    <IconChip icon={cat.icon} mode={m} size={36} />
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
  banner: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderRadius: radius.md, minHeight: 56 },
});
