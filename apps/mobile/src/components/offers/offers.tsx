// Offers from the Indian stores (ported from apps/web/src/components/offers/*).
// Shoppers must never miss an offer: every product offer is shown, tagged "In your price"
// (we use it when we buy) or "On the source store" (bank/card/EMI offers in India).
import { useEffect, useState } from "react";
import { AppState, FlatList, StyleSheet, View } from "react-native";
import Animated, { FadeInRight } from "react-native-reanimated";
import type { Offer, OfferType, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { useAccent, useTheme, withAlpha } from "@/theme";
import { Icon } from "../Icon";
import { IconChip, T } from "../ui";

export const OFFER_META: Record<OfferType, { icon: string; label: string }> = {
  coupon: { icon: "tag", label: "Coupon" },
  deal: { icon: "zap", label: "Deal" },
  lightning: { icon: "zap", label: "Lightning deal" },
  bank: { icon: "credit-card", label: "Bank offer" },
  cashback: { icon: "dollar-sign", label: "Cashback" },
  emi: { icon: "clock", label: "No Cost EMI" },
  partner: { icon: "award", label: "Partner offer" },
  combo: { icon: "package", label: "Combo" },
  special_price: { icon: "star", label: "Special price" },
  subscribe: { icon: "refresh-cw", label: "Subscribe & save" },
  exchange: { icon: "repeat", label: "Exchange" },
  other: { icon: "info", label: "Offer" },
};

/** The single most important deal signal for a product card, if any. */
export function headlineDeal(p: Product): { label: string; endsAt?: string } | null {
  const live = (p.offers ?? []).find((o) => (o.type === "lightning" || o.type === "deal") && (!o.endsAt || Date.parse(o.endsAt) > Date.now()));
  if (live) return { label: live.type === "lightning" ? "Lightning deal" : live.title || "Limited time deal", endsAt: live.endsAt ?? p.dealEndsAt ?? undefined };
  if (p.dealBadge) return { label: p.dealBadge, endsAt: p.dealEndsAt ?? undefined };
  return null;
}

// ---------- countdown: one shared 1 s ticker, paused while the app is in the background ----------
const subs = new Set<() => void>();
let timer: ReturnType<typeof setInterval> | null = null;
let active = AppState.currentState === "active";

function startTicker() {
  if (timer || !active || !subs.size) return;
  timer = setInterval(() => subs.forEach((fn) => fn()), 1000);
}
function stopTicker() {
  if (timer) clearInterval(timer);
  timer = null;
}
AppState.addEventListener("change", (s) => {
  active = s === "active";
  if (active) {
    subs.forEach((fn) => fn()); // jump straight to the right time on return
    startTicker();
  } else stopTicker();
});

function useTick() {
  const [, set] = useState(0);
  useEffect(() => {
    const fn = () => set((n) => n + 1);
    subs.add(fn);
    startTicker();
    return () => {
      subs.delete(fn);
      if (!subs.size) stopTicker();
    };
  }, []);
}

export function countdownText(endsAt: string) {
  const ms = Math.max(0, Date.parse(endsAt) - Date.now());
  if (!Number.isFinite(ms) || ms === 0) return { text: "Deal ended", ended: true, label: "Deal ended" };
  const h = Math.floor(ms / 3.6e6);
  const m = Math.floor((ms % 3.6e6) / 6e4);
  const s = Math.floor((ms % 6e4) / 1000);
  const pad = (n: number) => String(n).padStart(2, "0");
  return {
    text: h >= 48 ? `${Math.floor(h / 24)} days` : `${pad(h)}:${pad(m)}:${pad(s)}`,
    ended: false,
    label: `Ends in ${h} hours ${m} minutes`,
  };
}

export function Countdown({ endsAt, compact, color, size = "tiny" }: { endsAt: string; compact?: boolean; color?: string; size?: "tiny" | "small" }) {
  useTick();
  const c = countdownText(endsAt);
  return (
    <T v={size} accessibilityLabel={c.label} style={{ color, fontWeight: "800", fontVariant: ["tabular-nums"] }}>
      {c.ended || compact ? c.text : `Ends in ${c.text}`}
    </T>
  );
}

// ---------- product card deal line ----------
export function DealBadge({ p }: { p: Product }) {
  const { c } = useTheme();
  const deal = headlineDeal(p);
  if (!deal) return null;
  return (
    <View style={[styles.deal, { backgroundColor: c.bad }]} accessibilityLabel={deal.label}>
      <Icon name="zap" size={10} color="#fff" />
      <T v="tiny" tone="white" numberOfLines={1} style={{ fontWeight: "800", fontSize: 10, textTransform: "uppercase", flexShrink: 1 }}>
        {deal.label}
      </T>
      {deal.endsAt && (
        <>
          <T v="tiny" tone="white" style={{ fontSize: 10 }}>
            ·
          </T>
          <Countdown endsAt={deal.endsAt} compact color="#fff" />
        </>
      )}
    </View>
  );
}

export function OfferHint({ p }: { p: Product }) {
  const { c } = useTheme();
  const a = useAccent(p.market);
  const n = p.offers?.length ?? 0;
  if (p.couponInr)
    return (
      <T v="tiny" style={{ color: c.good, fontWeight: "800" }} numberOfLines={1}>
        ₹{Math.round(p.couponInr)} coupon already applied
      </T>
    );
  if (n > 0)
    return (
      <View style={{ flexDirection: "row", alignItems: "center", gap: 3 }}>
        <Icon name="tag" size={11} color={a.ink} />
        <T v="tiny" style={{ color: a.ink, fontWeight: "800" }} numberOfLines={1}>
          {n} {n === 1 ? "offer" : "offers"} on the source store
        </T>
      </View>
    );
  return null;
}

// ---------- product screen section ----------
function OfferCard({ o, i, p }: { o: Offer; i: number; p: Product }) {
  const { c } = useTheme();
  const meta = OFFER_META[o.type] ?? OFFER_META.other;
  return (
    <Animated.View entering={FadeInRight.delay(i * 50).springify().damping(18)} style={[styles.card, { backgroundColor: c.surface, borderColor: o.appliesToOurPrice ? withAlpha(c.good, 0.5) : c.line }]}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <IconChip icon={meta.icon} size={32} mode={p.market} color={o.appliesToOurPrice ? c.good : undefined} />
        <T v="small" numberOfLines={1} style={{ flex: 1, fontWeight: "800" }}>
          {o.title || meta.label}
        </T>
        {o.count && o.count > 1 ? (
          <View style={[styles.tag, { backgroundColor: c.surface2 }]}>
            <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>
              {o.count} offers
            </T>
          </View>
        ) : null}
      </View>
      {!!o.detail && (
        <T v="tiny" tone="ink2" numberOfLines={3} style={{ lineHeight: 16 }}>
          {o.detail}
        </T>
      )}
      <View style={{ flex: 1 }} />
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
        {o.appliesToOurPrice ? (
          <View style={[styles.tag, { backgroundColor: withAlpha(c.good, 0.14) }]}>
            <Icon name="check" size={11} color={c.good} />
            <T v="tiny" style={{ color: c.good, fontWeight: "800" }}>
              In your price
            </T>
          </View>
        ) : (
          <View style={[styles.tag, { backgroundColor: c.surface2 }]}>
            <Icon name="info" size={11} color={c.ink2} />
            <T v="tiny" tone="ink2" style={{ fontWeight: "800" }}>
              On the source store
            </T>
          </View>
        )}
        {o.endsAt && (
          <View style={[styles.tag, { backgroundColor: withAlpha(c.bad, 0.12) }]}>
            <Icon name="clock" size={11} color={c.bad} />
            <Countdown endsAt={o.endsAt} compact color={c.bad} />
          </View>
        )}
        {typeof o.claimedPct === "number" && (
          <View style={[styles.tag, { backgroundColor: withAlpha(c.warn, 0.14) }]}>
            <T v="tiny" style={{ color: c.warn, fontWeight: "800" }}>
              {Math.round(o.claimedPct)}% claimed
            </T>
          </View>
        )}
      </View>
    </Animated.View>
  );
}

export function OffersSection({ p }: { p: Product }) {
  const a = useAccent(p.market);
  const offers = [...(p.offers ?? [])].sort((x, y) => Number(y.appliesToOurPrice) - Number(x.appliesToOurPrice));
  if (!offers.length) return null;
  const applied = offers.filter((o) => o.appliesToOurPrice).length;
  const source = STORES[p.market].source;
  return (
    <View style={{ gap: 8, marginBottom: 16 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 16 }}>
        <Icon name="tag" size={18} color={a.ink} />
        <T v="h3" accessibilityRole="header">
          Offers on {source}
        </T>
        <View style={[styles.count, { backgroundColor: a.accent }]}>
          <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
            {offers.length}
          </T>
        </View>
      </View>
      <FlatList
        horizontal
        data={offers}
        keyExtractor={(_, i) => String(i)}
        renderItem={({ item, index }) => <OfferCard o={item} i={index} p={p} />}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, gap: 10, paddingVertical: 2 }}
        snapToInterval={250}
        decelerationRate="fast"
      />
      <T v="tiny" tone="ink3" style={{ paddingHorizontal: 16 }}>
        {applied > 0 ? "Coupons and deal prices we can use are already in your price. " : ""}
        Bank, card and EMI offers apply when paying on {source} in India; we show them so you see the full deal.
      </T>
    </View>
  );
}

const styles = StyleSheet.create({
  deal: { flexDirection: "row", alignItems: "center", gap: 3, alignSelf: "flex-start", maxWidth: "100%", paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6 },
  card: { width: 240, minHeight: 150, padding: 12, borderRadius: 18, gap: 8, borderWidth: 1 },
  tag: { flexDirection: "row", alignItems: "center", gap: 4, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  count: { paddingHorizontal: 7, paddingVertical: 1, borderRadius: 999 },
});
