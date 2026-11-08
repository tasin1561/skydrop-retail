import { memo } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import type { Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct } from "@/lib/pricing";
import { unitMrp, unitPrice } from "@/lib/cart";
import { sized } from "@/lib/variants";
import { radius, useTheme } from "@/theme";
import { useStore } from "@/state/store";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { DealBadge, OfferHint } from "./offers/offers";
import { ProductImage, Skeleton, Stars, T, Tap } from "./ui";
import { useT } from "@/i18n";

export const ProductCard = memo(function ProductCard({ p, width }: { p: Product; width: number }) {
  const { t, bdt, digits } = useT();
  const { c } = useTheme();
  const { wish, toggleWish } = useStore();
  const off = discountPct(p);
  const price = unitPrice(p);
  const mrp = unitMrp(p);
  const wished = wish.includes(p.id);
  const source = STORES[p.market].source;

  // The wishlist heart is a sibling of the card button, not a child: nested buttons are invalid HTML on web and
  // VoiceOver/TalkBack can't reach a button inside another accessible element.
  return (
    <View style={{ width }}>
      <Tap
        onPress={() => router.push(`/product/${p.id}`)}
        accessibilityLabel={t("m.cardA11y", { title: p.title, price: bdt(price), source })}
        style={[styles.card, { width, backgroundColor: c.surface, borderColor: c.line }]}
      >
        <View>
          <ProductImage uri={sized(p.image, "SL400")} placeholder={p.placeholder} style={{ width: width - 2, height: width - 2, borderTopLeftRadius: radius.lg - 1, borderTopRightRadius: radius.lg - 1 }} />
          {off >= 10 && (
            <View style={[styles.off, { backgroundColor: c.primary }]}>
              <T v="tiny" style={{ fontWeight: "500", color: c.onPrimary, fontVariant: ["tabular-nums"] }}>
                -{digits(off)}%
              </T>
            </View>
          )}
        </View>
        <View style={{ padding: 10, gap: 4 }}>
          <T v="tiny" tone="ink3" numberOfLines={1}>
            {t("m.fromSource", { source })}
          </T>
          <T v="small" numberOfLines={2} style={{ minHeight: 36 }}>
            {p.title}
          </T>
          {p.rating > 0 && <Stars rating={p.rating} count={p.reviews} />}
          <DealBadge p={p} />
          <View style={{ flexDirection: "row", alignItems: "baseline", gap: 6, flexWrap: "wrap" }}>
            <T v="h3" style={{ fontWeight: "600", fontVariant: ["tabular-nums"] }}>
              {bdt(price)}
            </T>
            {mrp > price && (
              <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>
                {bdt(mrp)}
              </T>
            )}
            {off > 0 && (
              <T v="tiny" tone="good" style={{ fontWeight: "500" }}>
                {t("card.off", { pct: off })}
              </T>
            )}
          </View>
          <OfferHint p={p} />
        </View>
      </Tap>
      <Tap
        onPress={() => {
          haptic.light();
          toggleWish(p.id);
        }}
        accessibilityLabel={wished ? t("wish.remove") : t("wish.save")}
        accessibilityState={{ selected: wished }}
        hitSlop={6}
        style={[styles.heart, { backgroundColor: c.surface }]}
      >
        <Icon name="heart" size={16} color={wished ? c.ink : c.ink3} />
      </Tap>
    </View>
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
  card: { borderRadius: radius.lg, borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  off: { position: "absolute", top: 8, left: 8, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
  heart: { position: "absolute", top: 4, right: 4, width: 40, height: 40, borderRadius: 18, alignItems: "center", justifyContent: "center" },
  origin: { alignSelf: "flex-start", paddingHorizontal: 7, paddingVertical: 2, borderRadius: 999, maxWidth: "100%" },
});
