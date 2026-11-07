// Product page sections: price breakdown, delivery route, specs, reviews.
import { useEffect, useState } from "react";
import { LayoutAnimation, Pressable, StyleSheet, View } from "react-native";
import Animated, { Easing, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import type { MarketId, Product } from "@/lib/types";
import { PRICING, buyPriceInr, deliveryWindow, priceBdt } from "@/lib/pricing";
import { STORES } from "@/lib/markets";
import { addDays } from "@/lib/cart";
import { useAccent, useTheme } from "@/theme";
import { Icon } from "../Icon";
import { Card, Divider, IconChip, T } from "../ui";
import { useT } from "@/i18n";


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
  const { t, bdt, inr, digits } = useT();
  const source = STORES[p.market].source;
  const total = p.priceBdt ?? priceBdt(p);
  const buyInr = buyPriceInr(p);
  const converted = buyInr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const importPct = PRICING.importPct[p.category] ?? 0.1;
  const duty = converted * importPct;
  const service = total - Math.round(converted) - Math.round(duty);
  const { baseBdt, perKgBdt, freeAboveBdt } = PRICING.shipping;
  const rows = [
    { icon: "tag", label: t("pb.priceOn", { source }), note: t("pb.fxNote", { inr: inr(buyInr) + (buyInr < p.priceInr ? t("m.afterCoupon") : ""), rate: "৳" + digits(PRICING.fxInrToBdt.toFixed(2)) }), value: bdt(converted) },
    { icon: "shield", label: t("pb.duty"), note: t("pb.dutyNote", { pct: Math.round(importPct * 100), category: p.category }), value: bdt(duty) },
    { icon: "star", label: t("pb.service"), note: t("pb.serviceNote"), value: bdt(service) },
  ];
  return (
    <Accordion title={t("pb.title")} icon="file-text" meta={t("pb.meta")} market={p.market}>
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
          <T v="h3">{t("pb.youPay")}</T>
          <T v="h3" style={{ fontWeight: "800" }}>
            {bdt(total)}
          </T>
        </View>
        <T v="tiny" tone="ink2">
          {t("pb.shipping")}: {t("pb.shippingRule", { base: bdt(baseBdt), perKg: bdt(perKgBdt), free: bdt(freeAboveBdt) })} {t("pb.verified")} {t("pb.verifiedNote", { source })}
        </T>
      </View>
    </Accordion>
  );
}

/** India store -> Skydrop Kolkata hub -> your door, with a parcel travelling along the route. */
export function DeliveryRoute({ market }: { market: MarketId }) {
  const { c } = useTheme();
  const a = useAccent(market);
  const { t, lang } = useT();
  const { min, max } = deliveryWindow();
  const now = new Date().toISOString();
  const [w, setW] = useState(0);
  const prog = useSharedValue(0);
  useEffect(() => {
    prog.value = withRepeat(withTiming(1, { duration: 3200, easing: Easing.inOut(Easing.cubic) }), -1, false);
  }, [prog]);
  const parcel = useAnimatedStyle(() => ({ transform: [{ translateX: prog.value * Math.max(0, w - 24) }], opacity: prog.value < 0.05 || prog.value > 0.95 ? 0.4 : 1 }));
  const stops = [
    { icon: "shopping-bag", title: STORES[market].source, sub: t("ws.store") },
    { icon: "package", title: t("ws.hub"), sub: t("m.hubSub") },
    { icon: "home", title: t("dc.door"), sub: t("dc.doorBody") },
  ];
  return (
    <Card style={{ gap: 14 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
        <IconChip icon="truck" mode={market} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>
            {t("m.deliveredIn", { min, max })}
          </T>
          <T v="tiny" tone="ink2">
            {t("m.orderToday", { from: addDays(now, min, lang), to: addDays(now, max, lang) })}
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
  const { t } = useT();
  if (!specs.length && !bullets.length) return null;
  return (
    <Accordion title={t("pd.about")} icon="list" meta={bullets.length && specs.length ? t("m.highlightsSpecs") : bullets.length ? t("m.highlights") : t("pd.specs")} market={p.market} initiallyOpen>
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
  const { t, digits, num } = useT();
  const a = useAccent(p.market);
  const list = p.reviewList ?? [];
  const bars = (p.starBreakdown ?? []).filter((r) => r.percentage >= 0).sort((x, y) => y.rating - x.rating);
  if (!list.length && !bars.length) return null;
  return (
    <Card style={{ gap: 14 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
        <View style={{ alignItems: "center" }}>
          <T v="h1">{digits(p.rating.toFixed(1))}</T>
          <T v="tiny" tone="ink3">
            {t("m.ratingsN", { count: num(p.reviews) })}
          </T>
        </View>
        <View style={{ flex: 1, gap: 4 }}>
          {bars.map((b) => (
            <View key={b.rating} style={{ flexDirection: "row", alignItems: "center", gap: 6 }} accessibilityLabel={t("m.starsPct", { rating: b.rating, pct: b.percentage })}>
              <T v="tiny" tone="ink2" style={{ width: 14 }}>
                {digits(b.rating)}
              </T>
              <View style={{ flex: 1, height: 6, borderRadius: 3, backgroundColor: c.surface2, overflow: "hidden" }}>
                <View style={{ width: `${b.percentage}%`, height: 6, backgroundColor: a.accent }} />
              </View>
              <T v="tiny" tone="ink3" style={{ width: 30, textAlign: "right" }}>
                {digits(b.percentage)}%
              </T>
            </View>
          ))}
        </View>
      </View>
      <T v="tiny" tone="ink3">
        {t("m.reviewsFrom", { source: STORES[p.market].source })}
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
