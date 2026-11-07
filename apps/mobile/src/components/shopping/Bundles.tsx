// Bundles, frequently bought together and the group-buy invite on the product screen (features "bundles",
// "group_orders"). Mirrors apps/web/src/components/shopping/{BoughtTogether,ProductBundles,AddBundleButton}.tsx.
import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import Animated, { useAnimatedStyle, withTiming, useReducedMotion } from "react-native-reanimated";
import { useFeature } from "@/lib/features";
import { STORES } from "@/lib/markets";
import { priceBdt } from "@/lib/pricing";
import { cartLine, groupSavingPreview, shoppingMore, type BoughtTogether, type Bundle } from "@/lib/shopping";
import { sized } from "@/lib/variants";
import { haptic } from "@/lib/haptics";
import type { Product } from "@/lib/types";
import { useStore } from "@/state/store";
import { radius, useAccent, useTheme } from "@/theme";
import { Button, Card, IconChip, ProductImage, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useT } from "@/i18n";
import { DUR, timing, listEnter } from "@/lib/motion";

/** Checkbox that pops (transform only; Reduce Motion → instant). */
function Check({ on }: { on: boolean }) {
  const { c } = useTheme();
  const reduce = useReducedMotion();
  const s = useAnimatedStyle(() => ({ transform: [{ scale: reduce ? 1 : on ? withTiming(1, timing(DUR.open)) : withTiming(0.85, { duration: 120 }) }] }), [on, reduce]);
  return (
    <Animated.View style={[styles.check, { borderColor: on ? c.brand : c.line, backgroundColor: on ? c.brand : c.surface }, s]}>
      {on && <Icon name="check" size={14} color="#fff" />}
    </Animated.View>
  );
}

/** "Frequently bought together": checklist, live total and "Add all N to cart". */
export function BoughtTogetherCard({ data, canBuyAnchor }: { data: BoughtTogether; canBuyAnchor: boolean }) {
  const { addToCart } = useStore();
  const toast = useToast();
  const { t, bdt } = useT();
  const all = [data.product, ...data.items];
  const [on, setOn] = useState<Record<string, boolean>>(() => Object.fromEntries(all.map((p, i) => [p.id, i > 0 || canBuyAnchor])));
  const picked = all.filter((p) => on[p.id]);
  const total = picked.reduce((s, p) => s + priceBdt(p), 0);

  function addAll() {
    haptic.success();
    for (const p of picked) addToCart(cartLine(p));
    toast({ kind: "success", title: t("fbt.added", { count: picked.length }), body: t("fbt.inTotal", { amount: bdt(total) }), action: { label: t("buy.viewCart"), href: "/cart" } });
  }

  return (
    <Card style={{ gap: 10 }}>
      <View style={styles.head}>
        <IconChip icon="shopping-bag" />
        <T v="h3" accessibilityRole="header" style={{ flex: 1 }}>{data.source === "copurchase" ? t("fbt.title") : t("fbt.goesWell")}</T>
      </View>
      {all.map((p, i) => {
        const disabled = i === 0 && !canBuyAnchor;
        const checked = !!on[p.id];
        return (
          <Tap
            key={p.id}
            disabled={disabled}
            onPress={() => {
              haptic.tap();
              setOn((s) => ({ ...s, [p.id]: !s[p.id] }));
            }}
            accessibilityRole="checkbox"
            accessibilityState={{ checked, disabled }}
            accessibilityLabel={t("mx.fbtToggle", { title: p.title, price: bdt(priceBdt(p)) })}
            scaleTo={0.98}
            style={[styles.row, { opacity: checked ? 1 : 0.6 }]}
          >
            <Check on={checked} />
            <ProductImage uri={sized(p.image, "SL160")} placeholder={p.placeholder} size={48} style={{ borderRadius: 10 }} />
            <View style={{ flex: 1 }}>
              <T v="small" numberOfLines={2}>
                {i === 0 ? <T v="small" style={{ fontWeight: "800" }}>{t("fbt.thisItem")}</T> : null}
                {p.title}
              </T>
              {i > 0 && (
                <T v="tiny" tone="brand" style={{ fontWeight: "700" }} onPress={() => router.push(`/product/${encodeURIComponent(p.id)}`)} accessibilityRole="link" suppressHighlighting>
                  {t("common.view")}
                </T>
              )}
            </View>
            <T v="small" style={{ fontWeight: "800", fontVariant: ["tabular-nums"] }}>{bdt(priceBdt(p))}</T>
          </Tap>
        );
      })}
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10, marginTop: 4 }}>
        <View style={{ flex: 1 }}>
          <T v="tiny" tone="ink2">{t("fbt.totalFor", { count: picked.length })}</T>
          <T v="h2" style={{ fontVariant: ["tabular-nums"] }}>{bdt(total)}</T>
        </View>
        <Button
          label={picked.length === all.length ? t("fbt.addAll", { count: picked.length }) : t("fbt.addN", { count: picked.length })}
          icon="shopping-cart"
          disabled={!picked.length}
          onPress={addAll}
          style={{ flex: 1.4 }}
        />
      </View>
    </Card>
  );
}

/** Compact bundle card (product screen and bundle list). */
export function BundleCard({ b, index = 0 }: { b: Bundle; index?: number }) {
  const { c } = useTheme();
  const { t, bdt } = useT();
  return (
    <Animated.View entering={listEnter(index)}>
      <Tap onPress={() => router.push(`/bundles/${encodeURIComponent(b.slug)}`)} accessibilityLabel={t("mx.bundleOpen", { title: b.title })} scaleTo={0.98}>
        <View style={[styles.bundle, { backgroundColor: c.surface2 }]}>
          {!!b.items?.length && (
            <View style={{ flexDirection: "row" }}>
              {b.items.slice(0, 4).map(({ product }, i) => (
                <View key={product.id} style={[styles.stack, { marginLeft: i ? -12 : 0, borderColor: c.surface }]}>
                  <ProductImage uri={sized(product.image, "SL160")} placeholder={product.placeholder} size={52} style={{ borderRadius: 10 }} />
                </View>
              ))}
            </View>
          )}
          <View>
            <T v="h3" numberOfLines={2}>{b.title}</T>
            <T v="tiny" tone="ink2">{t("bundle.meta", { count: b.count, pct: b.discountPct })}</T>
          </View>
          <View style={{ flexDirection: "row", alignItems: "flex-end", justifyContent: "space-between", gap: 8 }}>
            <View style={{ flexDirection: "row", alignItems: "baseline", gap: 6 }}>
              <T v="h2" style={{ fontVariant: ["tabular-nums"] }}>{bdt(b.totalBdt)}</T>
              <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>{bdt(b.regularBdt)}</T>
            </View>
            <View style={[styles.save, { backgroundColor: c.good }]}>
              <T v="tiny" tone="white" style={{ fontWeight: "800" }}>{t("bundle.save", { amount: bdt(b.discountBdt) })}</T>
            </View>
          </View>
        </View>
      </Tap>
    </Animated.View>
  );
}

/** Adds every bundle item to the cart; the bundle saving is applied in the cart quote and at checkout. */
export function useAddBundle() {
  const { addToCart } = useStore();
  const toast = useToast();
  const { t, bdt } = useT();
  return (b: Bundle) => {
    haptic.success();
    for (const i of b.items ?? []) addToCart(cartLine(i.product, i.qty));
    toast({ kind: "success", title: t("bundle.added"), body: t("bundle.saveAtCheckout", { amount: bdt(b.discountBdt) }), action: { label: t("buy.viewCart"), href: "/cart" } });
  };
}

/** Product screen: FBT + curated bundles that include this product. */
export function ProductBundlesSection({ p, canBuy }: { p: Product; canBuy: boolean }) {
  const on = useFeature("bundles");
  const { t } = useT();
  const [fbt, setFbt] = useState<BoughtTogether | null>(null);
  const [bundles, setBundles] = useState<Bundle[]>([]);

  useEffect(() => {
    if (!on) return;
    let live = true;
    shoppingMore
      .boughtTogether(p.id, 3)
      .then((r) => live && setFbt(r))
      .catch(() => live && setFbt(null));
    shoppingMore
      .productBundles(p.id)
      .then((r) => live && setBundles(r))
      .catch(() => live && setBundles([]));
    return () => {
      live = false;
    };
  }, [on, p.id]);

  if (!on) return null;
  return (
    <>
      {fbt && fbt.items.length > 0 && <BoughtTogetherCard key={fbt.product.id} data={fbt} canBuyAnchor={canBuy} />}
      {bundles.length > 0 && (
        <Card style={{ gap: 10 }}>
          <View style={styles.head}>
            <IconChip icon="package" />
            <T v="h3" accessibilityRole="header" style={{ flex: 1 }}>{t("bundle.title")}</T>
          </View>
          {bundles.map((b, i) => (
            <BundleCard key={b.id} b={b} index={i} />
          ))}
        </Card>
      )}
    </>
  );
}

/** Product screen: "Buy with friends" → adds this item and opens the group starter for its store. */
export function GroupBuyCard({ p, canBuy, line }: { p: Product; canBuy: boolean; line: () => void }) {
  const on = useFeature("group_orders");
  const { demo, cart } = useStore();
  const a = useAccent(p.market);
  const toast = useToast();
  const { t, bdt } = useT();
  if (!on || demo || !canBuy) return null;
  const mine = cart.filter((i) => i.market === p.market);
  const saving = groupSavingPreview(mine.length ? mine : [cartLine(p)]);
  return (
    <Tap
      onPress={() => {
        haptic.light();
        if (!cart.some((i) => i.productId === p.id)) {
          line();
          toast({ kind: "success", title: t("buy.added", { count: 1 }), body: t("mx.grpItemAdded") });
        }
        router.push({ pathname: "/group", params: { market: p.market } });
      }}
      accessibilityLabel={`${t("mx.grpProductT")}. ${t("mx.grpStartHere")}`}
      scaleTo={0.98}
    >
      <Card style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
        <IconChip icon="users" mode={p.market} />
        <View style={{ flex: 1, gap: 2 }}>
          <T v="h3" style={{ fontSize: 15 }}>{t("mx.grpProductT")}</T>
          <T v="tiny" tone="ink2">
            {saving > 0 ? t("grp.startOn", { store: STORES[p.market].name, amount: bdt(saving) }) : t("mx.grpProductB", { store: STORES[p.market].name })}
          </T>
        </View>
        <Icon name="arrow-right" size={18} color={a.ink} />
      </Card>
    </Tap>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: "row", alignItems: "center", gap: 10 },
  row: { flexDirection: "row", alignItems: "center", gap: 10, minHeight: 64, paddingVertical: 4 },
  check: { width: 24, height: 24, borderRadius: 7, borderWidth: 2, alignItems: "center", justifyContent: "center" },
  bundle: { gap: 10, padding: 12, borderRadius: radius.md },
  stack: { borderWidth: 2, borderRadius: 12 },
  save: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999 },
});
