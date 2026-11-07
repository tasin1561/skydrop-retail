import { useCallback, useState } from "react";
import { Alert, RefreshControl, ScrollView, View } from "react-native";
import { Stack, router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import type { Order } from "@/lib/types";
import { canCancel, currentStatus, etaRange, isInProgress } from "@/lib/orderStatus";
import { STORES } from "@/lib/markets";
import { sized } from "@/lib/variants";
import { useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, Divider, Empty, IconChip, ProductImage, Skeleton, T, Tap } from "@/components/ui";
import { StatusPill, Timeline, TrackBar, useNow } from "@/components/OrderBits";
import { useToast } from "@/components/Toast";
import { RouteCard } from "@/components/RouteCard";
import { fulfilment, localTracking, type Tracking } from "@/api/fulfilment";
import { useFeature } from "@/lib/features";
import { useT, type MsgKey } from "@/i18n";
import { bdName } from "@/lib/bd";

const PAY: Record<string, MsgKey | "bKash" | "Nagad"> = { bkash: "bKash", nagad: "Nagad", card: "pay.card", advance50: "m.payHalf" };

export default function OrderDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { c } = useTheme();
  const tr = useT();
  const { t, bdt, lang } = tr;
  const insets = useSafeAreaInsets();
  const { demo } = useStore();
  const toast = useToast();
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const now = useNow();
  // fulfilment: route map + returns
  const mapOn = useFeature("live_tracking_map");
  const returnsOn = useFeature("returns");
  const [tracking, setTracking] = useState<Tracking | null>(null);

  const load = useCallback(async () => {
    try {
      setOrder(await api.order(id));
      setError(null);
      setTracking(await fulfilment.tracking(id));
    } catch (e) {
      setError((e as Error).message);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (error) return <Empty icon="alert-circle" title={t("od.notFound")} text={error} action={t("m.backToOrders")} onAction={() => router.back()} />;
  if (!order)
    return (
      <View style={{ padding: 16, gap: 12 }}>
        <Skeleton h={120} r={20} />
        <Skeleton h={320} r={20} />
      </View>
    );

  const status = demo ? currentStatus(order, now) : order.status;
  const shown: Order = { ...order, status };

  const cancel = () =>
    Alert.alert(t("od.cancelQ"), t("m.cancelFreeBody"), [
      { text: t("m.keepOrder"), style: "cancel" },
      {
        text: t("od.cancel"),
        style: "destructive",
        onPress: async () => {
          setCancelling(true);
          try {
            const o = await api.cancelOrder(order.id);
            if (o) setOrder(o);
            haptic.success();
            toast({ kind: "info", title: t("od.cancelledT") });
          } catch (e) {
            toast({ kind: "error", title: t("m.cantCancel"), body: (e as Error).message });
          } finally {
            setCancelling(false);
          }
        },
      },
    ]);

  return (
    <ScrollView
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }}
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
    >
      <Stack.Screen options={{ title: order.id }} />
      <Card style={{ gap: 12 }}>
        <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
          <View>
            <T v="tiny" tone="ink3">{t("m.skydropTracking")}</T>
            <T v="h3" selectable>
              {order.trackingCode}
            </T>
          </View>
          <StatusPill status={status} />
        </View>
        <TrackBar status={status} />
        <T v="small" tone="ink2">
          {isInProgress(status) ? t("m.etaLine", { range: etaRange(order, tr) }) : status === "delivered" ? t("od.deliveredMsg") : t("st.cancelled")}
        </T>
        {demo && isInProgress(status) && (
          <T v="tiny" tone="ink3">{t("m.demoTrack")}</T>
        )}
      </Card>

      {mapOn && status !== "cancelled" && <RouteCard tracking={tracking ?? localTracking(order, status)} />}

      <Card>
        <T v="h2" accessibilityRole="header" style={{ marginBottom: 14 }}>{t("os.tracking")}</T>
        <Timeline order={shown} />
      </Card>

      <Card style={{ gap: 12 }}>
        <T v="h2" accessibilityRole="header">{t("od.items")}</T>
        {order.items.map((i) => (
          <Tap key={i.productId + (i.variant ?? "")} onPress={() => router.push(`/product/${i.productId}`)} style={{ flexDirection: "row", gap: 12, alignItems: "center" }} accessibilityLabel={t("m.openItem", { title: i.title })}>
            <ProductImage uri={sized(i.image, "SL200")} placeholder={i.placeholder} size={60} style={{ borderRadius: 12 }} />
            <View style={{ flex: 1 }}>
              <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
                {i.title}
              </T>
              <T v="tiny" tone="ink2">
                {t("m.itemLine", { source: STORES[i.market].source, qty: i.qty })}
                {i.variant ? ` · ${i.variant}` : ""}
              </T>
            </View>
            <T v="small" style={{ fontWeight: "800" }}>
              {bdt(i.unitBdt * i.qty)}
            </T>
          </Tap>
        ))}
        <Divider />
        {[
          [t("od.items"), bdt(order.subtotal)],
          [t("sum.shipping"), order.shipping ? bdt(order.shipping) : t("common.free")],
          ...(order.discount - (order.pointsDiscount ?? 0) > 0 ? [[t("od.discount"), "-" + bdt(order.discount - (order.pointsDiscount ?? 0))]] : []),
          ...(order.pointsDiscount ? [[t("sum.points", { count: order.pointsRedeemed ?? 0 }), "-" + bdt(order.pointsDiscount)]] : []),
        ].map(([k, v]) => (
          <View key={k} style={{ flexDirection: "row", justifyContent: "space-between" }}>
            <T v="small" tone="ink2">
              {k}
            </T>
            <T v="small" style={{ fontWeight: "700" }}>
              {v}
            </T>
          </View>
        ))}
        <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
          <T v="h3">{t("sum.total")}</T>
          <T v="h2">{bdt(order.total)}</T>
        </View>
        {!!order.pointsEarned && status !== "cancelled" && (
          <T v="tiny" style={{ color: c.brand, fontWeight: "800" }}>
            {status === "delivered" ? t("m.earned", { count: order.pointsEarned }) : t("sum.earn", { count: order.pointsEarned })}
          </T>
        )}
      </Card>

      <Card style={{ gap: 10 }}>
        <View style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
          <IconChip icon="map-pin" />
          <View style={{ flex: 1 }}>
            <T v="h3" style={{ fontSize: 14 }}>
              {order.address.name} · {order.address.phone}
            </T>
            <T v="small" tone="ink2">
              {order.address.line}, {order.address.area}, {bdName(order.address.district, lang)}, {bdName(order.address.division, lang)}
            </T>
          </View>
        </View>
        <View style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
          <IconChip icon="credit-card" />
          <T v="small" style={{ flex: 1 }}>
            {PAY[order.payment] ? (PAY[order.payment] === "bKash" || PAY[order.payment] === "Nagad" ? PAY[order.payment] : t(PAY[order.payment] as MsgKey)) : order.payment}
            {order.paymentStatus ? t("m.payStatus", { status: order.paymentStatus }) : ""}
          </T>
        </View>
      </Card>

      {returnsOn && status === "delivered" && (
        <Button label={t("m.returnItem")} kind="ghost" icon="rotate-ccw" onPress={() => router.push(`/return/${order.id}`)} />
      )}
      {canCancel(status) && <Button label={t("od.cancel")} kind="danger" icon="x" busy={cancelling} onPress={cancel} />}
    </ScrollView>
  );
}
