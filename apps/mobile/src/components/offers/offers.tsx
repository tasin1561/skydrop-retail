// Offers from the Indian stores (ported from apps/web/src/components/offers/*).
// Shoppers must never miss an offer: every product offer is shown, tagged t("offer.inPrice")
// (we use it when we buy) or t("offer.onSource") (bank/card/EMI offers in India).
import { useEffect, useState } from "react";
import { AppState, FlatList, StyleSheet, View } from "react-native";
import Animated from "react-native-reanimated";
import type { Offer, OfferType, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { useAccent, useTheme, withAlpha } from "@/theme";
import { Icon } from "../Icon";
import { IconChip, T } from "../ui";
import { useT } from "@/i18n";
import { makeT, translate, type MsgKey, type TFn, type Translator } from "@/i18n/core";
import { listEnter } from "@/lib/motion";

/** label is a dictionary key. */
export const OFFER_META: Record<OfferType, { icon: string; label: MsgKey }> = {
  coupon: { icon: "tag", label: "offer.coupon" },
  deal: { icon: "zap", label: "offer.deal" },
  lightning: { icon: "zap", label: "offer.lightning" },
  bank: { icon: "credit-card", label: "offer.bank" },
  cashback: { icon: "dollar-sign", label: "offer.cashback" },
  emi: { icon: "clock", label: "offer.emi" },
  partner: { icon: "award", label: "offer.partner" },
  combo: { icon: "package", label: "offer.combo" },
  special_price: { icon: "star", label: "offer.special" },
  subscribe: { icon: "refresh-cw", label: "offer.subscribe" },
  exchange: { icon: "repeat", label: "offer.exchange" },
  other: { icon: "info", label: "offer.other" },
};

/** The single most important deal signal for a product card, if any. */
export function headlineDeal(p: Product, t: TFn = (k) => translate("en", k)): { label: string; endsAt?: string } | null {
  const live = (p.offers ?? []).find((o) => (o.type === "lightning" || o.type === "deal") && (!o.endsAt || Date.parse(o.endsAt) > Date.now()));
  if (live) return { label: live.type === "lightning" ? t("offer.lightning") : live.title || t("offer.limited"), endsAt: live.endsAt ?? p.dealEndsAt ?? undefined };
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

export function countdownText(endsAt: string, tr: Translator = makeT("en")) {
  const { t, digits } = tr;
  const ms = Math.max(0, Date.parse(endsAt) - Date.now());
  if (!Number.isFinite(ms) || ms === 0) return { text: t("cd.ended"), ended: true, label: t("cd.ended") };
  const h = Math.floor(ms / 3.6e6);
  const m = Math.floor((ms % 3.6e6) / 6e4);
  const s = Math.floor((ms % 6e4) / 1000);
  const pad = (n: number) => String(n).padStart(2, "0");
  return {
    text: h >= 48 ? t("cd.days", { count: Math.floor(h / 24) }) : digits(`${pad(h)}:${pad(m)}:${pad(s)}`),
    ended: false,
    label: t("cd.endsInAria", { h, m }),
  };
}

export function Countdown({ endsAt, compact, color, size = "tiny" }: { endsAt: string; compact?: boolean; color?: string; size?: "tiny" | "small" }) {
  useTick();
  const tr = useT();
  const c = countdownText(endsAt, tr);
  return (
    <T v={size} accessibilityLabel={c.label} style={{ color, fontWeight: "800", fontVariant: ["tabular-nums"] }}>
      {c.ended || compact ? c.text : tr.t("cd.endsIn", { time: c.text })}
    </T>
  );
}

// ---------- product card deal line ----------
export function DealBadge({ p }: { p: Product }) {
  const { c } = useTheme();
  const { t } = useT();
  const deal = headlineDeal(p, t);
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
  const { t, inr } = useT();
  const { c } = useTheme();
  const a = useAccent(p.market);
  const n = p.offers?.length ?? 0;
  if (p.couponInr)
    return (
      <T v="tiny" style={{ color: c.good, fontWeight: "800" }} numberOfLines={1}>
        {t("card.couponApplied", { amount: inr(p.couponInr) })}
      </T>
    );
  if (n > 0)
    return (
      <View style={{ flexDirection: "row", alignItems: "center", gap: 3 }}>
        <Icon name="tag" size={11} color={a.ink} />
        <T v="tiny" style={{ color: a.ink, fontWeight: "800" }} numberOfLines={1}>
          {t("card.offers", { count: n })}
        </T>
      </View>
    );
  return null;
}

// ---------- product screen section ----------
function OfferCard({ o, i, p }: { o: Offer; i: number; p: Product }) {
  const { t } = useT();
  const { c } = useTheme();
  const meta = OFFER_META[o.type] ?? OFFER_META.other;
  return (
    <Animated.View entering={listEnter(i)} style={[styles.card, { backgroundColor: c.surface, borderColor: o.appliesToOurPrice ? withAlpha(c.good, 0.5) : c.line }]}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <IconChip icon={meta.icon} size={32} mode={p.market} color={o.appliesToOurPrice ? c.good : undefined} />
        <T v="small" numberOfLines={1} style={{ flex: 1, fontWeight: "800" }}>
          {o.title || t(meta.label)}
        </T>
        {o.count && o.count > 1 ? (
          <View style={[styles.tag, { backgroundColor: c.surface2 }]}>
            <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>
              {t("offer.count", { count: o.count })}
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
            <T v="tiny" style={{ color: c.good, fontWeight: "800" }}>{t("offer.inPrice")}</T>
          </View>
        ) : (
          <View style={[styles.tag, { backgroundColor: c.surface2 }]}>
            <Icon name="info" size={11} color={c.ink2} />
            <T v="tiny" tone="ink2" style={{ fontWeight: "800" }}>{t("offer.onSource")}</T>
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
              {t("offer.claimed", { pct: Math.round(o.claimedPct) })}
            </T>
          </View>
        )}
      </View>
    </Animated.View>
  );
}

export function OffersSection({ p }: { p: Product }) {
  const { t, digits } = useT();
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
          {t("offer.title", { source })}
        </T>
        <View style={[styles.count, { backgroundColor: a.accent }]}>
          <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
            {digits(offers.length)}
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
        {applied > 0 ? t("offer.applied") : ""}
        {t("offer.bankNote", { source })}
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
