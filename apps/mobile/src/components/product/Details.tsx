// Product page sections: price breakdown, delivery route, specs, reviews.
import { useEffect, useState } from "react";
import { LayoutAnimation, Pressable, StyleSheet, View } from "react-native";
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import type { MarketId, Product } from "@/lib/types";
import { PRICING, buyPriceInr, deliveryWindow, formatBdt, priceBdt } from "@/lib/pricing";
import { STORES } from "@/lib/markets";
import { addDays } from "@/lib/cart";
import { useAccent, useTheme } from "@/theme";
import { Icon } from "../Icon";
import { Card, Divider, IconChip, T } from "../ui";


export function Accordion({ title, icon, meta, children, market, initiallyOpen }: { title: string; icon: string; meta?: string; children: React.ReactNode; market: MarketId; initiallyOpen?: boolean }) {
  const { c } = useTheme();
  const [open, setOpen] = useState(!!initiallyOpen);
  const rot = useSharedValue(initiallyOpen ? 180 : 0);
  const chevron = useAnimatedStyle(() => ({ transform: [{ rotate: `${rot.value}deg` }] }));
  return (
    <Card pad={0}>
      <Pressable
        onPress={() => {
          LayoutAnimation.configureNext(LayoutAnimation.create(220, "easeInEaseOut", "opacity"));
          rot.value = withTiming(open ? 0 : 180, { duration: 220 });
          setOpen(!open);
        }}
        accessibilityRole="button"
        accessibilityState={{ expanded: open }}
        accessibilityLabel={title}
        style={styles.accHead}
      >
        <IconChip icon={icon} mode={market} size={36} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>
            {title}
          </T>
          {meta && (
            <T v="tiny" tone="ink2">
              {meta}
            </T>
          )}
        </View>
        <Animated.View style={chevron}>
          <Icon name="chevron-down" size={20} color={c.ink3} />
        </Animated.View>
      </Pressable>
      {open && <View style={{ paddingHorizontal: 14, paddingBottom: 14 }}>{children}</View>}
    </Card>
  );
}

export function PriceBreakdown({ p }: { p: Product }) {
  const source = STORES[p.market].source;
  const total = p.priceBdt ?? priceBdt(p);
  const buyInr = buyPriceInr(p);
  const converted = buyInr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const importPct = PRICING.importPct[p.category] ?? 0.1;
  const duty = converted * importPct;
  const service = total - Math.round(converted) - Math.round(duty);
  const { baseBdt, perKgBdt, freeAboveBdt } = PRICING.shipping;
  const rows = [
    { icon: "tag", label: `Price on ${source}`, note: `₹${buyInr.toLocaleString("en-IN")}${buyInr < p.priceInr ? " after store coupon" : ""} at ৳${PRICING.fxInrToBdt.toFixed(2)} per rupee, with FX protection`, value: formatBdt(converted) },
    { icon: "shield", label: "Import duty", note: `${Math.round(importPct * 100)}% for ${p.category}, paid by us at customs`, value: formatBdt(duty) },
    { icon: "star", label: "Skydrop service", note: "Buying in India, checks and packing", value: formatBdt(service) },
  ];
  return (
    <Accordion title="Price breakdown" icon="file-text" meta="Import duty and service included. No surprises at the door." market={p.market}>
      <View style={{ gap: 10 }}>
        {rows.map((r) => (
          <View key={r.label} style={styles.line}>
            <IconChip icon={r.icon} mode={p.market} size={30} />
            <View style={{ flex: 1 }}>
              <T v="small" style={{ fontWeight: "700" }}>
                {r.label}
              </T>
              <T v="tiny" tone="ink2">
                {r.note}
              </T>
            </View>
            <T v="small" style={{ fontWeight: "700" }}>
              {r.value}
            </T>
          </View>
        ))}
        <Divider />
        <View style={[styles.line, { justifyContent: "space-between" }]}>
          <T v="h3">Item price you pay</T>
          <T v="h3" style={{ fontWeight: "800" }}>
            {formatBdt(total)}
          </T>
        </View>
        <T v="tiny" tone="ink2">
          Skydrop shipping is added in your cart by weight: {formatBdt(baseBdt)} + {formatBdt(perKgBdt)}/kg per store shipment, free above {formatBdt(freeAboveBdt)}. Prices are verified live at checkout; if {source} changes the price, you see the new total before you pay.
        </T>
      </View>
    </Accordion>
  );
}

/** India store -> Skydrop Kolkata hub -> your door, with a parcel travelling along the route. */
export function DeliveryRoute({ market }: { market: MarketId }) {
  const { c } = useTheme();
  const a = useAccent(market);
  const { min, max } = deliveryWindow();
  const now = new Date().toISOString();
  const [w, setW] = useState(0);
  const t = useSharedValue(0);
  useEffect(() => {
    t.value = withRepeat(withTiming(1, { duration: 3200, easing: Easing.inOut(Easing.cubic) }), -1, false);
  }, [t]);
  const parcel = useAnimatedStyle(() => ({ transform: [{ translateX: t.value * Math.max(0, w - 24) }], opacity: t.value < 0.05 || t.value > 0.95 ? 0.4 : 1 }));
  const stops = [
    { icon: "shopping-bag", title: STORES[market].source, sub: "India store" },
    { icon: "package", title: "Kolkata hub", sub: "Skydrop checks & packs" },
    { icon: "home", title: "Your door", sub: "Anywhere in Bangladesh" },
  ];
  return (
    <Card style={{ gap: 14 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
        <IconChip icon="truck" mode={market} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>
            Delivered by Skydrop in {min}-{max} days
          </T>
          <T v="tiny" tone="ink2">
            Order today, arrives {addDays(now, min)} - {addDays(now, max)}
          </T>
        </View>
      </View>
      <View onLayout={(e) => setW(e.nativeEvent.layout.width)} style={{ height: 24, justifyContent: "center" }}>
        <View style={{ height: 2, borderRadius: 1, backgroundColor: a.soft, marginHorizontal: 12 }} />
        <View style={[StyleSheet.absoluteFill, { flexDirection: "row", justifyContent: "space-between", alignItems: "center", paddingHorizontal: 6 }]}>
          {stops.map((s) => (
            <View key={s.title} style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: a.accent, borderWidth: 3, borderColor: c.surface }} />
          ))}
        </View>
        <Animated.View style={[styles.parcel, { backgroundColor: a.accent }, parcel]}>
          <Icon name="package" size={12} color="#fff" />
        </Animated.View>
      </View>
      <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
        {stops.map((s, i) => (
          <View key={s.title} style={{ flex: 1, alignItems: i === 0 ? "flex-start" : i === 2 ? "flex-end" : "center" }}>
            <T v="tiny" style={{ fontWeight: "800" }} numberOfLines={1}>
              {s.title}
            </T>
            <T v="tiny" tone="ink3" numberOfLines={1}>
              {s.sub}
            </T>
          </View>
        ))}
      </View>
    </Card>
  );
}

export function Specs({ p }: { p: Product }) {
  const specs = Object.entries(p.specs ?? {}).filter(([k, v]) => v && !/customer reviews|best sellers rank/i.test(k));
  const bullets = (p.bullets ?? []).filter(Boolean);
  if (!specs.length && !bullets.length) return null;
  return (
    <Accordion title="About this item" icon="list" meta={`${bullets.length ? "Highlights" : ""}${bullets.length && specs.length ? " and " : ""}${specs.length ? "specifications" : ""}`} market={p.market} initiallyOpen>
      <View style={{ gap: 8 }}>
        {bullets.slice(0, 6).map((b, i) => (
          <View key={i} style={{ flexDirection: "row", gap: 8 }}>
            <T v="small" tone="ink3">
              •
            </T>
            <T v="small" tone="ink2" style={{ flex: 1 }}>
              {b}
            </T>
          </View>
        ))}
        {specs.length > 0 && bullets.length > 0 && <Divider style={{ marginVertical: 4 }} />}
        {specs.slice(0, 14).map(([k, v]) => (
          <View key={k} style={{ flexDirection: "row", gap: 10 }}>
            <T v="small" tone="ink3" style={{ width: "40%" }}>
              {k}
            </T>
            <T v="small" style={{ flex: 1, fontWeight: "600" }}>
              {v}
            </T>
          </View>
        ))}
      </View>
    </Accordion>
  );
}

export function Reviews({ p }: { p: Product }) {
  const { c } = useTheme();
  const a = useAccent(p.market);
  const list = p.reviewList ?? [];
  const bars = (p.starBreakdown ?? []).filter((r) => r.percentage >= 0).sort((x, y) => y.rating - x.rating);
  if (!list.length && !bars.length) return null;
  return (
    <Card style={{ gap: 14 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
        <View style={{ alignItems: "center" }}>
          <T v="h1">{p.rating.toFixed(1)}</T>
          <T v="tiny" tone="ink3">
            {p.reviews.toLocaleString("en-IN")} ratings
          </T>
        </View>
        <View style={{ flex: 1, gap: 4 }}>
          {bars.map((b) => (
            <View key={b.rating} style={{ flexDirection: "row", alignItems: "center", gap: 6 }} accessibilityLabel={`${b.rating} stars, ${b.percentage}%`}>
              <T v="tiny" tone="ink2" style={{ width: 14 }}>
                {b.rating}
              </T>
              <View style={{ flex: 1, height: 6, borderRadius: 3, backgroundColor: c.surface2, overflow: "hidden" }}>
                <View style={{ width: `${b.percentage}%`, height: 6, backgroundColor: a.accent }} />
              </View>
              <T v="tiny" tone="ink3" style={{ width: 30, textAlign: "right" }}>
                {b.percentage}%
              </T>
            </View>
          ))}
        </View>
      </View>
      <T v="tiny" tone="ink3">
        Reviews from shoppers on {STORES[p.market].source}
      </T>
      {list.slice(0, 5).map((r, i) => (
        <View key={i} style={{ gap: 4 }}>
          <Divider />
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, marginTop: 8 }}>
            <View style={{ flexDirection: "row" }}>
              {Array.from({ length: 5 }, (_, s) => (
                <Icon key={s} name="star" size={12} color={s < Math.round(r.rating) ? "#f59e0b" : c.line} />
              ))}
            </View>
            <T v="small" style={{ fontWeight: "800", flex: 1 }} numberOfLines={1}>
              {r.title}
            </T>
          </View>
          <T v="small" tone="ink2" numberOfLines={5}>
            {r.content}
          </T>
          <T v="tiny" tone="ink3">
            {r.author} · {r.date}
          </T>
        </View>
      ))}
    </Card>
  );
}

const styles = StyleSheet.create({
  accHead: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, minHeight: 64 },
  line: { flexDirection: "row", alignItems: "center", gap: 10 },
  parcel: { position: "absolute", left: 0, width: 24, height: 24, borderRadius: 12, alignItems: "center", justifyContent: "center" },
});
