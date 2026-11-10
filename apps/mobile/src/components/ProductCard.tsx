import { memo } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import type { Product } from "@/lib/types";
import { DELIVERY_DAYS, STORES } from "@/lib/markets";
import { discountPct } from "@/lib/pricing";
import { unitMrp, unitPrice } from "@/lib/cart";
import { sized } from "@/lib/variants";
import { radius, shadow, useTheme } from "@/theme";
import { useStore } from "@/state/store";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { DealBadge, OfferHint } from "./offers/offers";
import { ProductImage, Skeleton, T, Tap } from "./ui";
import { StoreName } from "./StoreName";
import { useT } from "@/i18n";

/** "20K+ bought in past month" (Amazon) → "20K+". */
function boughtCount(p: Product): string | null {
  const m = p.market === "amazon" ? /^([\d.,]+[kKmM]?\+?)/.exec((p.salesVolume ?? "").trim()) : null;
  return m ? m[1].toUpperCase() : null;
}

/**
 * Product card, phone first (same design as the web card): big photo with the discount, store and best-seller
 * badges on it, brand for fashion, a two-line title, the taka price with what it was, sizes, one line of rating and
 * sales, one line of India → Bangladesh delivery days.
 */
export const ProductCard = memo(function ProductCard({ p, width }: { p: Product; width: number }) {
  const { t, bdt, digits } = useT();
  const { c } = useTheme();
  const { wish, toggleWish } = useStore();
  const off = discountPct(p);
  const price = unitPrice(p);
  const mrp = unitMrp(p);
  const wished = wish.includes(p.id);
  const source = STORES[p.market].source;
  const fashion = p.market === "flipkart" || p.market === "myntra";
  const imgH = fashion ? Math.round(width * 1.25) : width;
  const sizes = fashion ? Array.from(new Set((p.variants ?? []).map((v) => v.dimensions?.Size ?? v.label).filter(Boolean))).slice(0, 4) : [];
  const bought = boughtCount(p);
  const [dMin, dMax] = DELIVERY_DAYS[p.market] ?? [7, 12];

  // The wishlist heart is a sibling of the card button, not a child: nested buttons are invalid HTML on web and
  // VoiceOver/TalkBack can't reach a button inside another accessible element.
  return (
    <View style={{ width }}>
      <Tap
        onPress={() => router.push(`/product/${p.id}`)}
        accessibilityLabel={t("m.cardA11y", { title: p.title, price: bdt(price), source })}
        style={[styles.card, { width, backgroundColor: c.surface }, shadow(c)]}
      >
        <View style={{ borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg, overflow: "hidden", backgroundColor: "#fff" }}>
          <ProductImage
            uri={sized(p.image, "SL400")}
            placeholder={p.placeholder}
            contentFit={fashion ? "cover" : "contain"}
            style={{ width, height: imgH }}
          />
          {off >= 5 && (
            <View style={[styles.off, { backgroundColor: "#e5363d" }]}>
              <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
                -{digits(off)}%
              </T>
            </View>
          )}
          <View style={styles.store}>
            <StoreName mode={p.market} variant="pill" tone="light" size={11} />
          </View>
          {p.bestSeller && (
            <View style={styles.best}>
              <T v="tiny" style={{ color: "#fff", fontWeight: "700", fontSize: 10, textTransform: "uppercase" }}>
                {t("card.bestSeller")}
              </T>
            </View>
          )}
        </View>
        <View style={{ padding: 10, gap: 3 }}>
          {fashion && !!p.brand && (
            <T v="tiny" tone="ink2" numberOfLines={1} style={{ fontWeight: "800", textTransform: "uppercase", letterSpacing: 0.4 }}>
              {p.brand}
            </T>
          )}
          <T v="small" numberOfLines={2} style={{ minHeight: 36 }}>
            {p.title}
          </T>
          <View style={{ flexDirection: "row", alignItems: "baseline", gap: 6, flexWrap: "wrap" }}>
            <T v="h3" style={{ fontWeight: "800" }}>
              {bdt(price)}
            </T>
            {mrp > price && (
              <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>
                {bdt(mrp)}
              </T>
            )}
            {off > 0 && (
              <T v="tiny" style={{ color: c.good, fontWeight: "800" }}>
                {t("card.off", { pct: digits(off) })}
              </T>
            )}
          </View>
          <OfferHint p={p} />
          <DealBadge p={p} />
          {sizes.length > 1 && (
            <T v="tiny" tone="ink2" numberOfLines={1} accessibilityLabel={t("card.sizes") + ": " + sizes.join(", ")}>
              {sizes.join(" · ")}
            </T>
          )}
          <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
            <Icon name="spark" size={12} color="#f59e0b" />
            <T v="tiny" style={{ fontWeight: "800" }}>
              {p.rating > 0 ? digits(p.rating.toFixed(1)) : t("card.new")}
            </T>
            {p.rating > 0 && !!p.reviews && (
              <T v="tiny" tone="ink2">
                ({digits(p.reviews >= 1000 ? (p.reviews / 1000).toFixed(1) + "k" : p.reviews)})
              </T>
            )}
            {bought && (
              <T v="tiny" tone="ink2" numberOfLines={1} style={{ flexShrink: 1 }}>
                · {t("card.bought", { count: bought })}
              </T>
            )}
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 4, marginTop: 1 }}>
            <Icon name="truck" size={12} color={c.ink2} />
            <T v="tiny" tone="ink2" numberOfLines={1} style={{ flexShrink: 1, fontWeight: "600" }}>
              {t("card.eta", { min: digits(dMin), max: digits(dMax) })}
            </T>
          </View>
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
        style={[styles.heart, { backgroundColor: c.surface }, shadow(c)]}
      >
        <Icon name="heart" size={16} color={wished ? c.coral : c.ink3} />
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
  card: { borderRadius: radius.lg },
  off: { position: "absolute", top: 8, left: 8, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6 },
  heart: { position: "absolute", top: 4, right: 4, width: 40, height: 40, borderRadius: 18, alignItems: "center", justifyContent: "center" },
  // solid white under the tinted pill, so the store name stays readable over any photo
  store: { position: "absolute", left: 8, bottom: 8, maxWidth: "80%", backgroundColor: "#fff", borderRadius: 999 },
  best: { position: "absolute", right: 8, bottom: 8, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, backgroundColor: "rgba(20,20,26,0.85)" },
});
