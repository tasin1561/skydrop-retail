// One curated bundle: items, regular vs bundle price, "Add bundle" (adds every item; the saving applies in the cart).
// Mirrors apps/web/src/app/bundles/[slug]/page.tsx.
import { useEffect, useState } from "react";
import { ScrollView, StyleSheet, View } from "react-native";
import { router, Stack, useLocalSearchParams } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated from "react-native-reanimated";
import { useFeature } from "@/lib/features";
import { STORES } from "@/lib/markets";
import { priceBdt } from "@/lib/pricing";
import { shoppingMore, type Bundle } from "@/lib/shopping";
import { sized } from "@/lib/variants";
import { useAccent, useTheme } from "@/theme";
import { Button, Card, Empty, ProductImage, Skeleton, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useAddBundle } from "@/components/shopping/Bundles";
import { useT } from "@/i18n";
import { listEnter } from "@/lib/motion";

export default function BundleScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const on = useFeature("bundles");
  const { t, bdt } = useT();
  const { c } = useTheme();
  const a = useAccent("all");
  const insets = useSafeAreaInsets();
  const add = useAddBundle();
  const [b, setB] = useState<Bundle | null | undefined>(undefined);

  useEffect(() => {
    if (!on) return;
    let live = true;
    shoppingMore
      .bundle(String(slug))
      .then((r) => live && setB(r))
      .catch(() => live && setB(null));
    return () => {
      live = false;
    };
  }, [on, slug]);

  if (!on) return <Empty icon="package" title={t("na.title", { title: t("bundles.title") })} text={t("na.later")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (b === undefined)
    return (
      <View style={{ padding: 16, gap: 12, backgroundColor: c.bg, flex: 1 }}>
        <Skeleton h={200} r={22} />
        <Skeleton h={90} />
        <Skeleton h={90} />
      </View>
    );
  if (b === null) return <Empty icon="package" title={t("bundle.notFound")} text={t("bundles.none")} action={t("bundle.all")} onAction={() => router.replace("/bundles")} />;
  const items = b.items ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <Stack.Screen options={{ title: b.title }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 120, gap: 12 }}>
        <Card pad={0} style={{ overflow: "hidden" }}>
          <LinearGradient colors={[a.heroA, a.heroA]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ padding: 18, gap: 8 }}>
            <View style={styles.badge}>
              <Icon name="package" size={13} color="#fff" />
              <T v="tiny" tone="white" style={{ fontWeight: "800" }}>{t("bundle.badge", { count: b.count })}</T>
            </View>
            <T v="h1" tone="white" accessibilityRole="header">{b.title}</T>
            {!!b.description && <T v="small" style={{ color: "rgba(255,255,255,0.88)" }}>{b.description}</T>}
          </LinearGradient>
          <View style={{ padding: 16, gap: 2 }}>
            <T v="h1" style={{ fontVariant: ["tabular-nums"] }}>{bdt(b.totalBdt)}</T>
            <T v="small" tone="ink2">
              <T v="small" tone="ink3" style={{ textDecorationLine: "line-through" }}>{bdt(b.regularBdt)}</T>
              {" · "}
              <T v="small" tone="good" style={{ fontWeight: "800" }}>{t("bundle.saveExtra", { amount: bdt(b.discountBdt), pct: b.discountPct })}</T>
            </T>
          </View>
        </Card>

        {items.map(({ product: p, qty }, i) => (
          <Animated.View key={p.id} entering={listEnter(i)}>
            <Tap onPress={() => router.push(`/product/${encodeURIComponent(p.id)}`)} accessibilityLabel={p.title} scaleTo={0.98}>
              <Card style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                <ProductImage uri={sized(p.image, "SL200")} placeholder={p.placeholder} size={72} style={{ borderRadius: 12 }} />
                <View style={{ flex: 1, gap: 2 }}>
                  <T v="tiny" tone="ink3" style={{ fontWeight: "700" }}>{STORES[p.market].name} · {STORES[p.market].source}</T>
                  <T v="small" numberOfLines={2}>{p.title}</T>
                  {qty > 1 && <T v="tiny" tone="ink2">{t("bundle.qty", { qty })}</T>}
                </View>
                <T v="small" style={{ fontWeight: "800", fontVariant: ["tabular-nums"] }}>{bdt(priceBdt(p) * qty)}</T>
              </Card>
            </Tap>
          </Animated.View>
        ))}
        <View style={{ flexDirection: "row", gap: 8, alignItems: "flex-start" }}>
          <Icon name="info" size={15} color={c.ink2} />
          <T v="tiny" tone="ink2" style={{ flex: 1 }}>{t("bundle.note")}</T>
        </View>
      </ScrollView>
      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 12) }]}>
        <Button
          label={b.available ? t("bundle.add", { amount: bdt(b.totalBdt) }) : t("bundle.oos")}
          icon="shopping-cart"
          disabled={!b.available || !items.length}
          onPress={() => {
            add(b);
            router.navigate("/cart");
          }}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: { flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start", backgroundColor: "rgba(255,255,255,0.2)", paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, paddingHorizontal: 16, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth },
});
