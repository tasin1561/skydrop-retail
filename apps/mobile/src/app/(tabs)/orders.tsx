import { useCallback, useState } from "react";
import { FlatList, RefreshControl, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router, useFocusEffect } from "expo-router";
import Animated, { FadeInDown } from "react-native-reanimated";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import type { Order } from "@/lib/types";
import { currentStatus, etaRange, isInProgress, storesOf } from "@/lib/orderStatus";
import { STORES } from "@/lib/markets";
import { sized } from "@/lib/variants";
import { useTheme } from "@/theme";
import { Card, Empty, ProductImage, Skeleton, T, Tap } from "@/components/ui";
import { StatusPill, TrackBar, useNow } from "@/components/OrderBits";
import { useT } from "@/i18n";

export default function Orders() {
  const tr = useT();
  const { t, bdt, digits, date } = tr;
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { user, demo } = useStore();
  const [orders, setOrders] = useState<Order[] | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const now = useNow();

  const load = useCallback(async () => {
    try {
      setOrders(await api.orders());
    } catch {
      setOrders([]);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      if (user || demo) load();
    }, [user, demo, load]),
  );

  if (!user && !demo)
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 40 }}>
        <Empty icon="package" title={t("ai.signInOrders")} text={t("m.ordersSignInText")} action={t("nav.signIn")} onAction={() => router.push("/login")} />
      </View>
    );

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 32, gap: 12 }}
      data={orders ?? []}
      keyExtractor={(o) => o.id}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          tintColor={c.brand}
          colors={[c.brand]}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
      ListHeaderComponent={
        <T v="h1" accessibilityRole="header" style={{ marginBottom: 4 }}>{t("nav.orders")}</T>
      }
      ListEmptyComponent={
        orders === null ? (
          <View style={{ gap: 12 }}>
            {[0, 1].map((i) => (
              <Skeleton key={i} h={150} r={20} />
            ))}
          </View>
        ) : (
          <Empty icon="package" title={t("ol.e.all")} text={t("m.noOrdersText")} action={t("hero.start")} onAction={() => router.navigate("/")} />
        )
      }
      renderItem={({ item: o, index }) => {
        const status = demo ? currentStatus(o, now) : o.status;
        return (
          <Animated.View entering={FadeInDown.delay(index * 50).springify().damping(18)}>
            <Tap onPress={() => router.push(`/order/${o.id}`)} accessibilityLabel={t("m.orderA11y", { id: o.id, total: bdt(o.total) })}>
              <Card style={{ gap: 12 }}>
                <View style={styles.row}>
                  <View>
                    <T v="h3">{digits(o.id)}</T>
                    <T v="tiny" tone="ink3">
                      {t("m.orderMeta", {
                        date: date(o.createdAt, { day: "numeric", month: "short", year: "numeric" }),
                        sources: storesOf(o)
                          .map((m) => STORES[m].source)
                          .join(", "),
                      })}
                    </T>
                  </View>
                  <StatusPill status={status} />
                </View>
                <View style={{ flexDirection: "row", gap: 8 }}>
                  {o.items.slice(0, 4).map((i) => (
                    <ProductImage key={i.productId + i.variant} uri={sized(i.image, "SL160")} placeholder={i.placeholder} size={52} style={{ borderRadius: 12 }} />
                  ))}
                  {o.items.length > 4 && (
                    <View style={[styles.more, { backgroundColor: c.surface2 }]}>
                      <T v="small" style={{ fontWeight: "700" }}>
                        +{digits(o.items.length - 4)}
                      </T>
                    </View>
                  )}
                </View>
                <TrackBar status={status} />
                <View style={styles.row}>
                  <T v="small" tone="ink2">
                    {isInProgress(status) ? t("m.arrives", { range: etaRange(o, tr) }) : status === "delivered" ? t("st.delivered.label") : t("st.cancelled")}
                  </T>
                  <T v="h3" style={{ fontWeight: "800" }}>
                    {bdt(o.total)}
                  </T>
                </View>
              </Card>
            </Tap>
          </Animated.View>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", gap: 8 },
  more: { width: 52, height: 52, borderRadius: 12, alignItems: "center", justifyContent: "center" },
});
