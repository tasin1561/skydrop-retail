// Curated bundles (GET /bundles, feature "bundles"). Mirrors apps/web/src/app/bundles/page.tsx.
import { useCallback, useState } from "react";
import { FlatList, RefreshControl, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useFeature } from "@/lib/features";
import { shoppingMore, type Bundle } from "@/lib/shopping";
import { useTheme } from "@/theme";
import { Card, Empty, Skeleton, T } from "@/components/ui";
import { BundleCard } from "@/components/shopping/Bundles";
import { useT } from "@/i18n";

export default function BundlesScreen() {
  const on = useFeature("bundles");
  const { t } = useT();
  const { c } = useTheme();
  const [items, setItems] = useState<Bundle[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(
    () =>
      shoppingMore
        .bundles()
        .then((r) => {
          setItems(r);
          setError(null);
        })
        .catch((e: Error) => {
          setError(e.message);
          setItems((cur) => cur ?? []);
        }),
    [],
  );
  useFocusEffect(
    useCallback(() => {
      if (on) load();
    }, [on, load]),
  );

  if (!on) return <Empty icon="package" title={t("na.title", { title: t("bundles.title") })} text={t("na.later")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (!items)
    return (
      <View style={{ padding: 16, gap: 12, backgroundColor: c.bg, flex: 1 }}>
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} h={170} />
        ))}
      </View>
    );
  return (
    <FlatList
      style={{ backgroundColor: c.bg }}
      data={items}
      keyExtractor={(b) => b.id}
      contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          tintColor={c.brand}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
      ListHeaderComponent={<T v="small" tone="ink2">{t("bundles.sub")}</T>}
      ListEmptyComponent={
        <Card>
          <T v="small" tone="ink2" style={{ textAlign: "center" }}>
            {error ?? t("bundles.none")}
          </T>
        </Card>
      }
      renderItem={({ item, index }) => (
        <Card pad={4}>
          <BundleCard b={item} index={index} />
        </Card>
      )}
    />
  );
}
