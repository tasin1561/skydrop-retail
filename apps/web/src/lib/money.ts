// Money module client: Skydrop Wallet, gift cards, Skydrop Plus and card EMI (docs/API.md "Wallet, gift cards, Plus, EMI").
// Every call needs NEXT_PUBLIC_API_URL; without it the pages show demo data so the storefront still works offline.
import { apiEnabled, apiRequest } from "./apiClient";
import type { CartItem } from "./types";

const { call, post } = apiRequest;

/* ---------------- types ---------------- */

export type WalletEntry = {
  id: number;
  type: "credit" | "debit";
  amountBdt: number;
  reason: "refund" | "gift_card" | "admin" | "promo" | "order_payment" | "expire";
  note?: string | null;
  orderId?: string | null;
  balanceAfterBdt: number;
  expiresAt?: string | null;
  at: string;
};
export type Wallet = {
  balanceBdt: number;
  expiringBdt: number;
  expiringAt: string | null;
  history: WalletEntry[];
  refund?: { default: "wallet" | "original"; bonusPct: number };
};

export type GiftCardConfig = { presets: number[]; minBdt: number; maxBdt: number; expiryMonths: number; designs: { key: string; label: string }[] };
export type GiftCard = {
  id: number;
  code?: string;
  last4: string;
  initialBdt: number;
  balanceBdt: number;
  design: string;
  status: "pending" | "active" | "redeemed" | "void" | "expired";
  recipientName?: string;
  message?: string;
  expiresAt?: string;
  createdAt?: string;
};

export type PlusBenefits = {
  freeShipping: boolean;
  freeShippingThresholdBdt: number;
  shippingDiscountPct: number;
  pointsMultiplier: number;
  extraDiscountPct: number;
  extraDiscountCapBdt: number;
  freeUnboxing: boolean;
  prioritySupport: boolean;
  earlyFlashSaleAccessMinutes: number;
  monthlyWalletCreditBdt: number;
  freeReturns: boolean;
};
export type PlusPeriod = "monthly" | "quarterly" | "yearly";
export type PlusPlan = {
  id: number;
  name: string;
  slug: string;
  priceBdt: number;
  period: PlusPeriod;
  months: number;
  monthlyBdt: number;
  highlight: boolean;
  tagline?: string | null;
  benefits: PlusBenefits;
  benefitList: string[];
};
export type PlusMembership = {
  id: number;
  status: "active" | "expired" | "cancelled";
  plan: PlusPlan | null;
  startedAt: string | null;
  renewsAt: string | null;
  cancelledAt: string | null;
  daysLeft: number;
};
export type PlusSavings = { totalBdt: number; ordersBdt: number; creditsBdt: number; orders: number; paidBdt: number };

export type EmiBank = { code: string; name: string; rates: Record<string, number> };
export type EmiConfig = { enabled: boolean; minOrderBdt: number; tenures: number[]; banks: EmiBank[] };
export type EmiPlan = {
  eligible: boolean;
  reason: string | null;
  tenure: number;
  bank: string | null;
  bankName: string | null;
  ratePct: number;
  chargeBdt: number;
  totalBdt: number;
  monthlyBdt: number;
};

export type MoneyLine = { key: string; label: string; amountBdt: number };
/** Money fields of POST /checkout/quote. */
export type MoneyQuote = {
  total: number;
  payNow: number;
  membershipDiscount: number;
  membership: { active: true; plan: { name: string; slug: string }; lines: MoneyLine[]; savedBdt: number; pointsMultiplier: number; bonusPoints: number } | null;
  plusPreview: { plan: { name: string; slug: string; priceBdt: number; period: PlusPeriod }; savedBdt: number; lines: MoneyLine[] } | null;
  wallet: { balanceBdt: number; appliedBdt: number } | null;
  walletApplied: number;
  emi: EmiPlan | null;
  emiCharge: number;
  emiAvailable: boolean;
  pointsToEarn: number;
};
export type PaymentStart = { status: string; redirectUrl?: string; amount?: number };

/* ---------------- demo data (no API) ---------------- */

export const DEMO_GIFT_CONFIG: GiftCardConfig = {
  presets: [500, 1000, 2000, 5000],
  minBdt: 200,
  maxBdt: 50000,
  expiryMonths: 12,
  designs: [
    { key: "classic", label: "Skydrop classic" },
    { key: "eid", label: "Eid Mubarak" },
    { key: "birthday", label: "Happy birthday" },
    { key: "thanks", label: "Thank you" },
    { key: "wedding", label: "Wedding" },
  ],
};

const demoBenefits = (b: Partial<PlusBenefits>): PlusBenefits => ({
  freeShipping: false,
  freeShippingThresholdBdt: 0,
  shippingDiscountPct: 0,
  pointsMultiplier: 1,
  extraDiscountPct: 0,
  extraDiscountCapBdt: 0,
  freeUnboxing: false,
  prioritySupport: false,
  earlyFlashSaleAccessMinutes: 0,
  monthlyWalletCreditBdt: 0,
  freeReturns: false,
  ...b,
});
const PLUS_LIST = [
  "Free Skydrop shipping on every order",
  "3% extra off items (up to ৳300 per order)",
  "2× Skydrop Points",
  "Flash sales 30 minutes early",
  "Priority support",
];
export const DEMO_PLANS: PlusPlan[] = [
  { id: 1, name: "Plus Monthly", slug: "plus-monthly", priceBdt: 299, period: "monthly", months: 1, monthlyBdt: 299, highlight: false, tagline: "Try it for a month",
    benefits: demoBenefits({ freeShipping: true, extraDiscountPct: 3, extraDiscountCapBdt: 300, pointsMultiplier: 2, earlyFlashSaleAccessMinutes: 30, prioritySupport: true }),
    benefitList: PLUS_LIST },
  { id: 2, name: "Plus Quarterly", slug: "plus-quarterly", priceBdt: 799, period: "quarterly", months: 3, monthlyBdt: 266, highlight: true, tagline: "Most popular",
    benefits: demoBenefits({ freeShipping: true, extraDiscountPct: 3, extraDiscountCapBdt: 300, pointsMultiplier: 2, earlyFlashSaleAccessMinutes: 30, prioritySupport: true, monthlyWalletCreditBdt: 50 }),
    benefitList: [...PLUS_LIST, "৳50 wallet credit every month"] },
  { id: 3, name: "Plus Yearly", slug: "plus-yearly", priceBdt: 2799, period: "yearly", months: 12, monthlyBdt: 233, highlight: false, tagline: "Best value",
    benefits: demoBenefits({ freeShipping: true, extraDiscountPct: 5, extraDiscountCapBdt: 500, pointsMultiplier: 2, earlyFlashSaleAccessMinutes: 60, prioritySupport: true, monthlyWalletCreditBdt: 100, freeReturns: true, freeUnboxing: true }),
    benefitList: ["Free Skydrop shipping on every order", "5% extra off items (up to ৳500 per order)", "2× Skydrop Points", "৳100 wallet credit every month", "Flash sales 60 minutes early", "Free unboxing video and free returns", "Priority support"] },
];

export const DEMO_EMI: EmiConfig = {
  enabled: false,
  minOrderBdt: 5000,
  tenures: [3, 6, 9, 12],
  banks: [
    { code: "brac", name: "BRAC Bank", rates: { "3": 3, "6": 4.5, "9": 6.5, "12": 8 } },
    { code: "city", name: "The City Bank (Amex)", rates: { "3": 3, "6": 5, "9": 7, "12": 9 } },
    { code: "ebl", name: "Eastern Bank", rates: { "3": 0, "6": 4.5, "9": 6.5, "12": 8.5 } },
    { code: "dbbl", name: "Dutch-Bangla Bank", rates: { "3": 3.5, "6": 5, "9": 7, "12": 9 } },
  ],
};

/* ---------------- EMI maths (same as App\Services\Money\EmiService) ---------------- */

export function emiPlan(cfg: EmiConfig, amount: number, tenure: number, bank?: string | null): EmiPlan {
  const base: EmiPlan = { eligible: false, reason: null, tenure, bank: bank ?? null, bankName: null, ratePct: 0, chargeBdt: 0, totalBdt: Math.round(amount), monthlyBdt: 0 };
  if (!cfg.enabled) return { ...base, reason: "EMI is not available right now." };
  if (amount < cfg.minOrderBdt) return { ...base, reason: `EMI is available on orders of ৳${cfg.minOrderBdt.toLocaleString("en-IN")} or more.` };
  if (!cfg.tenures.includes(tenure)) return { ...base, reason: "Choose one of the available EMI tenures." };
  const offering = cfg.banks.filter((b) => b.rates[String(tenure)] !== undefined);
  const b = bank ? cfg.banks.find((x) => x.code === bank) : [...offering].sort((x, y) => x.rates[String(tenure)] - y.rates[String(tenure)])[0];
  const rate = b ? b.rates[String(tenure)] : 0;
  if (b && rate === undefined) return { ...base, reason: `${b.name} does not offer a ${tenure}-month EMI.` };
  const charge = Math.ceil((amount * rate) / 100);
  const total = Math.round(amount) + charge;
  return { eligible: true, reason: null, tenure, bank: b?.code ?? null, bankName: b?.name ?? null, ratePct: rate, chargeBdt: charge, totalBdt: total, monthlyBdt: Math.ceil(total / tenure) };
}

/** Lowest monthly instalment for an amount, or null when not eligible ("EMI from ৳X/month"). */
export function emiFromMonthly(cfg: EmiConfig, amount: number): number | null {
  if (!cfg.enabled || amount < cfg.minOrderBdt || !cfg.tenures.length) return null;
  let best: number | null = null;
  for (const t of cfg.tenures) {
    const p = emiPlan(cfg, amount, t);
    if (p.eligible) best = best === null ? p.monthlyBdt : Math.min(best, p.monthlyBdt);
  }
  return best;
}

/* ---------------- helpers ---------------- */

/** XXXX-XXXX-XXXX-XXXX while typing (letters and digits only). */
export function formatGiftCode(raw: string) {
  return raw
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, "")
    .slice(0, 16)
    .replace(/(.{4})(?=.)/g, "$1-");
}

export const PERIOD_LABEL: Record<PlusPeriod, string> = { monthly: "month", quarterly: "3 months", yearly: "year" };

const items = (cart: CartItem[]) => cart.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt }));

/* ---------------- API ---------------- */

let emiCache: Promise<EmiConfig> | null = null;

export const money = {
  wallet: () => call<Wallet>("/wallet"),
  redeemGiftCard: (code: string) => post<{ amountBdt: number; balanceBdt: number }>("/gift-cards/redeem", { code }),
  giftCardConfig: () => (apiEnabled ? call<GiftCardConfig>("/gift-cards/config") : Promise.resolve(DEMO_GIFT_CONFIG)),
  myGiftCards: () => call<{ items: GiftCard[] }>("/gift-cards"),
  buyGiftCard: (body: {
    amountBdt: number;
    design: string;
    recipientName: string;
    recipientPhone?: string;
    recipientEmail?: string;
    message?: string;
    payment: "bkash" | "nagad" | "card";
    returnUrl: string;
  }) => post<{ giftCard: GiftCard; order: { id: string; total: number }; payment: PaymentStart }>("/gift-cards", body),

  plans: () => (apiEnabled ? call<{ items: PlusPlan[] }>("/membership/plans") : Promise.resolve({ items: DEMO_PLANS })),
  membership: () => call<{ membership: PlusMembership | null; benefits: PlusBenefits | null; savings: PlusSavings }>("/membership"),
  buyMembership: (plan: string, payment: "bkash" | "nagad" | "card", returnUrl: string) =>
    post<{ order: { id: string; total: number }; plan: PlusPlan; payment: PaymentStart }>("/membership", { plan, payment, returnUrl }),

  /** EMI config, fetched once per page load. */
  emiConfig: (): Promise<EmiConfig> => {
    if (!apiEnabled) return Promise.resolve(DEMO_EMI);
    emiCache ??= call<EmiConfig>("/emi").catch(() => {
      emiCache = null;
      return DEMO_EMI;
    });
    return emiCache;
  },

  /** Server quote with wallet / Plus / EMI applied (the cart maths in cart-math.ts has no money module). */
  quote: (
    cart: CartItem[],
    o: { coupon?: string; redeemPoints?: number; useWallet?: boolean; payment?: string; emiTenure?: number; emiBank?: string },
  ) => post<MoneyQuote>("/checkout/quote", { items: items(cart), ...o, coupon: o.coupon || undefined, redeemPoints: o.redeemPoints || undefined }),
};
