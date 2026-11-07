import { translate, type MsgKey, type TFn } from "@/i18n/core";
import type { Offer, OfferType, Product } from "@/lib/types";

export const OFFER_META: Record<OfferType, { icon: string; label: MsgKey }> = {
  coupon: { icon: "tag", label: "offer.coupon" },
  deal: { icon: "bolt", label: "offer.deal" },
  lightning: { icon: "bolt", label: "offer.lightning" },
  bank: { icon: "card", label: "offer.bank" },
  cashback: { icon: "wallet", label: "offer.cashback" },
  emi: { icon: "clock", label: "offer.emi" },
  partner: { icon: "verified", label: "offer.partner" },
  combo: { icon: "package", label: "offer.combo" },
  special_price: { icon: "spark", label: "offer.special" },
  subscribe: { icon: "refresh", label: "offer.subscribe" },
  exchange: { icon: "refresh", label: "offer.exchange" },
  other: { icon: "info", label: "offer.other" },
};

/** The single most important deal signal for a product card, if any. */
export function headlineDeal(p: Product, t: TFn = (k) => translate("en", k)): { label: string; endsAt?: string } | null {
  const live = (p.offers ?? []).find((o) => (o.type === "lightning" || o.type === "deal") && (!o.endsAt || Date.parse(o.endsAt) > Date.now()));
  if (live) return { label: live.type === "lightning" ? t("offer.lightning") : live.title || t("offer.limited"), endsAt: live.endsAt ?? p.dealEndsAt ?? undefined };
  if (p.dealBadge) return { label: p.dealBadge, endsAt: p.dealEndsAt ?? undefined };
  return null;
}

export function appliedOffers(p: Product): Offer[] {
  return (p.offers ?? []).filter((o) => o.appliesToOurPrice);
}

export function infoOffers(p: Product): Offer[] {
  return (p.offers ?? []).filter((o) => !o.appliesToOurPrice);
}
