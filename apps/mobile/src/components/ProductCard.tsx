import { memo } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import type { Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, formatBdt } from "@/lib/pricing";
import { unitMrp, unitPrice } from "@/lib/cart";
import { sized } from "@/lib/variants";
import { radius, shadow, useAccent, useTheme } from "@/theme";
import { useStore } from "@/state/store";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { DealBadge, OfferHint } from "./offers/offers";
import { ProductImage, Skeleton, Stars, T, Tap } from "./ui";
import { useT } from "@/i18n";

export const ProductCard = memo(function ProductCard({ p, width }: { p: Product; width: number }) {
  const { t, bdt, digits } = useT();
  const { c } = useTheme();
  const a = useAccent(p.market);
  const { wish, toggleWish } = useStore();
  const off = discountPct(p);
  const price = unitPrice(p);
  const mrp = unitMrp(p);
  const wished = wish.includes(p.id);
  const source = STORES[p.market].source;

  return (
    <Tap
      onPress={() => router.push(`/product/${p.id}`)}
      accessibilityLabel={t("m.cardA11y", { title: p.title, price: bdt(price), source })}
      style={[styles.card, { width, backgroundColor: c.surface }, shadow(c)]}
    >
      <View>
        <ProductImage uri={sized(p.image, "SL400")} placeholder={p.placeholder} style={{ width, height: width, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg }} />
        {off >= 10 && (
          <View style={[styles.off, { backgroundColor: c.coral }]}>
            <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
              -{digits(off)}%
            </T>
          </View>
        )}
        <Tap
          onPress={() => {
            haptic.light();
            toggleWish(p.id);
          }}
          accessibilityLabel={wished ? t("wish.remove") : t("wish.save")}
          accessibilityState={{ selected: wished }}
          hitSlop={6}
          style={[styles.heart, { backgroundColor: c.surface }, shadow(c)]}
        >
          <Icon name="heart" size={16} color={wished ? c.coral : c.ink3} />
        </Tap>
      </View>
      <View style={{ padding: 10, gap: 4 }}>
        <View style={[styles.origin, { backgroundColor: a.soft }]}>
          <T v="tiny" style={{ color: a.ink, fontWeight: "700" }} numberOfLines={1}>
            {t("m.fromSource", { source })}
          </T>
        </View>
        <T v="small" numberOfLines={2} style={{ fontWeight: "600", minHeight: 36 }}>
          {p.title}
        </T>
        {p.rating > 0 && <Stars rating={p.rating} count={p.reviews} />}
        <DealBadge p={p} />
        <View style={{ flexDirection: "row", alignItems: "baseline", gap: 6, flexWrap: "wrap" }}>
          <T v="h3" style={{ fontWeight: "800" }}>
            {formatBdt(price)}
          </T>
          {mrp > price && (
            <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>
              {formatBdt(mrp)}
            </T>
          )}
        </View>
        <OfferHint p={p} />
      </View>
    </Tap>
  );
});

export function ProductCardSkeleton({ width }: { width: number }) {
  const { c } = useTheme();
  return (
    <View style={[styles.card, { width, backgroundColor: c.surface }]}>
      <Skeleton w={width} h={width} r={radius.lg} />
      <View style={{ padding: 10, gap: 8 }}>
        <Skeleton w="60%" h={12} />
        <Skeleton h={12} />
        <Skeleton w="40%" h={16} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: radius.lg },
  off: { position: "absolute", top: 8, left: 8, paddingHorizontal: 7, paddingVertical: 3, borderRadius: 999 },
  heart: { position: "absolute", top: 6, right: 6, width: 36, height: 36, borderRadius: 18, alignItems: "center", justifyContent: "center" },
  origin: { alignSelf: "flex-start", paddingHorizontal: 7, paddingVertical: 2, borderRadius: 999, maxWidth: "100%" },
});
