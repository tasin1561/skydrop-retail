import type { Offer, OfferType, Product } from "@/lib/types";

export const OFFER_META: Record<OfferType, { icon: string; label: string }> = {
  coupon: { icon: "tag", label: "Coupon" },
  deal: { icon: "bolt", label: "Deal" },
  lightning: { icon: "bolt", label: "Lightning deal" },
  bank: { icon: "card", label: "Bank offer" },
  cashback: { icon: "wallet", label: "Cashback" },
  emi: { icon: "clock", label: "No Cost EMI" },
  partner: { icon: "verified", label: "Partner offer" },
  combo: { icon: "package", label: "Combo" },
  special_price: { icon: "spark", label: "Special price" },
  subscribe: { icon: "refresh", label: "Subscribe & save" },
  exchange: { icon: "refresh", label: "Exchange" },
  other: { icon: "info", label: "Offer" },
};

/** The single most important deal signal for a product card, if any. */
export function headlineDeal(p: Product): { label: string; endsAt?: string } | null {
  const live = (p.offers ?? []).find((o) => (o.type === "lightning" || o.type === "deal") && (!o.endsAt || Date.parse(o.endsAt) > Date.now()));
  if (live) return { label: live.type === "lightning" ? "Lightning deal" : live.title || "Limited time deal", endsAt: live.endsAt ?? p.dealEndsAt ?? undefined };
  if (p.dealBadge) return { label: p.dealBadge, endsAt: p.dealEndsAt ?? undefined };
  return null;
}

export function appliedOffers(p: Product): Offer[] {
  return (p.offers ?? []).filter((o) => o.appliesToOurPrice);
}

export function infoOffers(p: Product): Offer[] {
  return (p.offers ?? []).filter((o) => !o.appliesToOurPrice);
}
