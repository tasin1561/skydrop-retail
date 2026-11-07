import { useCallback, useState } from "react";
import { Alert, RefreshControl, ScrollView, View } from "react-native";
import { Stack, router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import type { Order } from "@/lib/types";
import { canCancel, currentStatus, etaRange, isInProgress } from "@/lib/orderStatus";
import { STORES } from "@/lib/markets";
import { formatBdt } from "@/lib/pricing";
import { sized } from "@/lib/variants";
import { useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, Divider, Empty, IconChip, ProductImage, Skeleton, T, Tap } from "@/components/ui";
import { StatusPill, Timeline, TrackBar, useNow } from "@/components/OrderBits";
import { useToast } from "@/components/Toast";

const PAY: Record<string, string> = { bkash: "bKash", nagad: "Nagad", card: "Card", advance50: "50% advance, rest on delivery" };

export default function OrderDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { demo } = useStore();
  const toast = useToast();
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const now = useNow();

  const load = useCallback(async () => {
    try {
      setOrder(await api.order(id));
      setError(null);
    } catch (e) {
      setError((e as Error).message);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (error) return <Empty icon="alert-circle" title="Order not found" text={error} action="Back to orders" onAction={() => router.back()} />;
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
    Alert.alert("Cancel this order?", "We haven't bought your items in India yet, so you can cancel for free.", [
      { text: "Keep order", style: "cancel" },
      {
        text: "Cancel order",
        style: "destructive",
        onPress: async () => {
          setCancelling(true);
          try {
            const o = await api.cancelOrder(order.id);
            if (o) setOrder(o);
            haptic.success();
            toast({ kind: "info", title: "Order cancelled" });
          } catch (e) {
            toast({ kind: "error", title: "Couldn't cancel", body: (e as Error).message });
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
            <T v="tiny" tone="ink3">
              Skydrop tracking
            </T>
            <T v="h3" selectable>
              {order.trackingCode}
            </T>
          </View>
          <StatusPill status={status} />
        </View>
        <TrackBar status={status} />
        <T v="small" tone="ink2">
          {isInProgress(status) ? `Estimated delivery ${etaRange(order)}` : status === "delivered" ? "Delivered. Thanks for shopping with Skydrop Retail." : "Cancelled"}
        </T>
        {demo && isInProgress(status) && (
          <T v="tiny" tone="ink3">
            Demo: tracking moves forward one stage every 2 minutes.
          </T>
        )}
      </Card>

      <Card>
        <T v="h2" accessibilityRole="header" style={{ marginBottom: 14 }}>
          Tracking
        </T>
        <Timeline order={shown} />
      </Card>

      <Card style={{ gap: 12 }}>
        <T v="h2" accessibilityRole="header">
          Items
        </T>
        {order.items.map((i) => (
          <Tap key={i.productId + (i.variant ?? "")} onPress={() => router.push(`/product/${i.productId}`)} style={{ flexDirection: "row", gap: 12, alignItems: "center" }} accessibilityLabel={`Open ${i.title}`}>
            <ProductImage uri={sized(i.image, "SL200")} placeholder={i.placeholder} size={60} style={{ borderRadius: 12 }} />
            <View style={{ flex: 1 }}>
              <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
                {i.title}
              </T>
              <T v="tiny" tone="ink2">
                From {STORES[i.market].source} · Qty {i.qty}
                {i.variant ? ` · ${i.variant}` : ""}
              </T>
            </View>
            <T v="small" style={{ fontWeight: "800" }}>
              {formatBdt(i.unitBdt * i.qty)}
            </T>
          </Tap>
        ))}
        <Divider />
        {[
          ["Items", formatBdt(order.subtotal)],
          ["Skydrop shipping", order.shipping ? formatBdt(order.shipping) : "Free"],
          ...(order.discount - (order.pointsDiscount ?? 0) > 0 ? [["Discount", "-" + formatBdt(order.discount - (order.pointsDiscount ?? 0))]] : []),
          ...(order.pointsDiscount ? [[`${order.pointsRedeemed ?? 0} Skydrop Points`, "-" + formatBdt(order.pointsDiscount)]] : []),
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
          <T v="h3">Total</T>
          <T v="h2">{formatBdt(order.total)}</T>
        </View>
        {!!order.pointsEarned && status !== "cancelled" && (
          <T v="tiny" style={{ color: c.brand, fontWeight: "800" }}>
            {status === "delivered" ? `You earned ${order.pointsEarned} Skydrop Points` : `You'll earn ${order.pointsEarned} Skydrop Points when it's delivered`}
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
              {order.address.line}, {order.address.area}, {order.address.district}, {order.address.division}
            </T>
          </View>
        </View>
        <View style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
          <IconChip icon="credit-card" />
          <T v="small" style={{ flex: 1 }}>
            {PAY[order.payment] ?? order.payment}
            {order.paymentStatus ? ` · payment ${order.paymentStatus}` : ""}
          </T>
        </View>
      </Card>

      {canCancel(status) && <Button label="Cancel order" kind="danger" icon="x" busy={cancelling} onPress={cancel} />}
    </ScrollView>
  );
}
