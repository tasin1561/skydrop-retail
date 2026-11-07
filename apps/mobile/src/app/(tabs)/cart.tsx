import { ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeOut, LinearTransition } from "react-native-reanimated";
import { router } from "expo-router";
import { useStore } from "@/state/store";
import { STORES } from "@/lib/markets";
import { PRICING, formatBdt } from "@/lib/pricing";
import { cartKey } from "@/lib/cart";
import { sized } from "@/lib/variants";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, Empty, IconButton, IconChip, ProductImage, Progress, T, Tap } from "@/components/ui";
import { QtyStepper } from "@/components/QtyStepper";
import { CouponField, SummaryLines } from "@/components/CartBits";
import { Icon } from "@/components/Icon";
import type { Group } from "@/lib/cart";

export default function Cart() {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { totals, cart } = useStore();

  if (!cart.length)
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 40 }}>
        <Empty icon="shopping-cart" title="Your cart is empty" text="Find something from India's biggest stores. We'll bring it to your door." action="Start shopping" onAction={() => router.navigate("/")} />
      </View>
    );

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 120, gap: 14 }}>
        <View>
          <T v="h1" accessibilityRole="header">
            Cart
          </T>
          <T v="small" tone="ink2">
            {totals.count} {totals.count === 1 ? "item" : "items"} from {totals.groups.length} {totals.groups.length === 1 ? "store" : "stores"}. Each store ships as one Skydrop parcel.
          </T>
        </View>
        {totals.groups.map((g) => (
          <StoreGroup key={g.market} g={g} />
        ))}
        <Card>
          <CouponField />
        </Card>
        <SummaryLines t={totals} />
      </ScrollView>
      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line }]}>
        <View style={{ flex: 1 }}>
          <T v="tiny" tone="ink3">
            Total
          </T>
          <T v="h2">{formatBdt(totals.total)}</T>
        </View>
        <Button
          label="Checkout"
          icon="arrow-right"
          onPress={() => {
            haptic.medium();
            router.push("/checkout");
          }}
          style={{ flex: 1.3 }}
        />
      </View>
    </View>
  );
}

function StoreGroup({ g }: { g: Group }) {
  const { c } = useTheme();
  const a = useAccent(g.market);
  const { setQty, removeItem } = useStore();
  const s = STORES[g.market];
  const free = g.toFree === 0;
  return (
    <Animated.View layout={LinearTransition.springify().damping(20)}>
      <Card style={{ gap: 12 }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
          <IconChip icon={s.icon} mode={g.market} />
          <View style={{ flex: 1 }}>
            <T v="h3">{s.name}</T>
            <T v="tiny" tone="ink2">
              Bought from {s.source} · ships with Skydrop
            </T>
          </View>
          <T v="small" style={{ fontWeight: "800" }}>
            {formatBdt(g.sub)}
          </T>
        </View>

        {g.items.map((i) => {
          const key = cartKey(i);
          return (
            <Animated.View key={key} exiting={FadeOut.duration(160)} layout={LinearTransition.springify().damping(20)} style={styles.item}>
              <Tap onPress={() => router.push(`/product/${i.productId}`)} accessibilityLabel={`Open ${i.title}`}>
                <ProductImage uri={sized(i.image, "SL200")} placeholder={i.placeholder} size={72} style={{ borderRadius: 14 }} />
              </Tap>
              <View style={{ flex: 1, gap: 4 }}>
                <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
                  {i.title}
                </T>
                {i.variant && (
                  <T v="tiny" tone="ink2" numberOfLines={1}>
                    {i.variant}
                  </T>
                )}
                <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginTop: 2 }}>
                  <QtyStepper small value={i.qty} onChange={(n) => setQty(key, n)} />
                  <T v="h3" style={{ fontWeight: "800" }}>
                    {formatBdt(i.unitBdt * i.qty)}
                  </T>
                </View>
              </View>
              <IconButton
                icon="trash-2"
                label={`Remove ${i.title}`}
                size={36}
                bg="transparent"
                tint={c.ink3}
                onPress={() => {
                  haptic.warn();
                  removeItem(key);
                }}
              />
            </Animated.View>
          );
        })}

        <View style={[styles.ship, { backgroundColor: a.soft }]}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <Icon name="truck" size={16} color={a.ink} />
            <T v="small" style={{ flex: 1, fontWeight: "700", color: a.ink }}>
              {free ? "Free Skydrop shipping unlocked" : `Add ${formatBdt(g.toFree)} for free shipping`}
            </T>
            <T v="small" style={{ fontWeight: "800" }}>
              {g.shipping ? formatBdt(g.shipping) : "Free"}
            </T>
          </View>
          <Progress value={g.sub / PRICING.shipping.freeAboveBdt} color={a.accent} track={c.surface} />
        </View>
      </Card>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  item: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  ship: { borderRadius: radius.md, padding: 12, gap: 8 },
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, flexDirection: "row", alignItems: "center", gap: 14, padding: 12, paddingHorizontal: 16, borderTopWidth: StyleSheet.hairlineWidth },
});
