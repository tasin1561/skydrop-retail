import { ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeOut, LinearTransition } from "react-native-reanimated";
import { router } from "expo-router";
import { useStore } from "@/state/store";
import { STORES } from "@/lib/markets";
import { PRICING } from "@/lib/pricing";
import { cartKey } from "@/lib/cart";
import { sized } from "@/lib/variants";
import { radius, useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, Empty, IconButton, IconChip, ProductImage, Progress, T, Tap } from "@/components/ui";
import { QtyStepper } from "@/components/QtyStepper";
import { CouponField, SummaryLines } from "@/components/CartBits";
import { Icon } from "@/components/Icon";
import type { Group } from "@/lib/cart";
import { useFeature } from "@/lib/features";
import { groupSavingPreview } from "@/lib/shopping";
import { useT } from "@/i18n";

export default function Cart() {
  const { t, bdt } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { totals, cart } = useStore();

  if (!cart.length)
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 40 }}>
        <Empty icon="shopping-cart" title={t("m.cartEmpty")} text={t("m.cartEmptyText")} action={t("hero.start")} onAction={() => router.navigate("/")} />
      </View>
    );

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 120, gap: 14 }}>
        <View>
          <T v="h1" accessibilityRole="header">{t("nav.cart")}</T>
          <T v="small" tone="ink2">
            {t("m.cartLine", { items: t("cart.items", { count: totals.count }), stores: t("cart.stores", { count: totals.groups.length }) })}
          </T>
        </View>
        {totals.groups.map((g) => (
          <StoreGroup key={g.market} g={g} />
        ))}
        <GroupInvite />
        <Card>
          <CouponField />
        </Card>
        <SummaryLines t={totals} />
      </ScrollView>
      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line }]}>
        <View style={{ flex: 1 }}>
          <T v="tiny" tone="ink3">{t("sum.total")}</T>
          <T v="h2">{bdt(totals.total)}</T>
        </View>
        <Button
          label={t("cart.checkout")}
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
  const { t, bdt } = useT();
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
              {t("m.groupMeta", { source: s.source })}
            </T>
          </View>
          <T v="small" style={{ fontWeight: "800" }}>
            {bdt(g.sub)}
          </T>
        </View>

        {g.items.map((i) => {
          const key = cartKey(i);
          return (
            <Animated.View key={key} exiting={FadeOut.duration(160)} layout={LinearTransition.springify().damping(20)} style={styles.item}>
              <Tap onPress={() => router.push(`/product/${i.productId}`)} accessibilityLabel={t("m.openItem", { title: i.title })}>
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
                    {bdt(i.unitBdt * i.qty)}
                  </T>
                </View>
              </View>
              <IconButton
                icon="trash-2"
                label={t("cart.removeItem", { title: i.title })}
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
              {free ? t("m.freeShipUnlocked") : t("m.addForFree", { amount: bdt(g.toFree) })}
            </T>
            <T v="small" style={{ fontWeight: "800" }}>
              {g.shipping ? bdt(g.shipping) : t("common.free")}
            </T>
          </View>
          <Progress value={g.sub / PRICING.shipping.freeAboveBdt} color={a.accent} track={c.surface} />
        </View>
      </Card>
    </Animated.View>
  );
}

/** Group buy invite for the biggest single-store basket (feature "group_orders"), like the web cart. */
function GroupInvite() {
  const on = useFeature("group_orders");
  const { t, bdt } = useT();
  const { c } = useTheme();
  const { totals, demo } = useStore();
  if (!on || demo) return null;
  const g = [...totals.groups].sort((x, y) => y.items.length - x.items.length)[0];
  const save = g ? groupSavingPreview(g.items) : 0;
  if (!g || save <= 0) return null;
  return (
    <Tap onPress={() => router.push({ pathname: "/group", params: { market: g.market } })} accessibilityLabel={t("grp.share")} scaleTo={0.98}>
      <Card style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
        <IconChip icon="users" mode={g.market} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>{t("grp.share")}</T>
          <T v="tiny" tone="ink2">{t("grp.startOn", { store: STORES[g.market].name, amount: bdt(save) })}</T>
        </View>
        <Icon name="arrow-right" size={18} color={c.brand} />
      </Card>
    </Tap>
  );
}

const styles = StyleSheet.create({
  item: { flexDirection: "row", gap: 12, alignItems: "flex-start" },
  ship: { borderRadius: radius.md, padding: 12, gap: 8 },
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, flexDirection: "row", alignItems: "center", gap: 14, padding: 12, paddingHorizontal: 16, borderTopWidth: StyleSheet.hairlineWidth },
});
