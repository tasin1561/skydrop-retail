// Money module: Skydrop Wallet, gift cards, Skydrop Plus (docs/API.md "Wallet, gift cards, Plus, EMI").
// Demo mode (no API): empty wallet, sample plans, purchases disabled.
import { http, isDemo } from "./client";

export type WalletEntry = { id: number; type: "credit" | "debit"; amountBdt: number; reason: string; note?: string | null; orderId?: string | null; at: string };
export type Wallet = { balanceBdt: number; expiringBdt: number; expiringAt: string | null; history: WalletEntry[] };
export type PlusPeriod = "monthly" | "quarterly" | "yearly";
export type PlusPlan = { id: number; name: string; slug: string; priceBdt: number; period: PlusPeriod; months: number; monthlyBdt: number; highlight: boolean; tagline?: string | null; benefitList: string[] };
export type PlusMembership = { status: string; plan: PlusPlan | null; renewsAt: string | null };
export type PlusSavings = { totalBdt: number; ordersBdt: number; creditsBdt: number; orders: number; paidBdt: number };

const DEMO_PLANS: PlusPlan[] = [
  { id: 1, name: "Plus Monthly", slug: "plus-monthly", priceBdt: 299, period: "monthly", months: 1, monthlyBdt: 299, highlight: false, tagline: "Try it for a month",
    benefitList: ["Free Skydrop shipping on every order", "3% extra off items", "2× Skydrop Points"] },
  { id: 2, name: "Plus Yearly", slug: "plus-yearly", priceBdt: 2799, period: "yearly", months: 12, monthlyBdt: 233, highlight: true, tagline: "Best value",
    benefitList: ["Free Skydrop shipping on every order", "5% extra off items", "2× Skydrop Points", "৳100 wallet credit every month"] },
];

export type EmiBank = { code: string; name: string; rates: Record<string, number> };
export type EmiConfig = { enabled: boolean; minOrderBdt: number; tenures: number[]; banks: EmiBank[] };
export type EmiPlan = { eligible: boolean; reason: string | null; tenure: number; bank: string | null; bankName: string | null; ratePct: number; chargeBdt: number; totalBdt: number; monthlyBdt: number };
export type EmiChoice = { tenure: number; bank: string };
export type MoneyLine = { key: string; label: string; amountBdt: number };
/** Money fields of POST /checkout/quote (server totals with wallet, Plus, EMI and unboxing). */
export type MoneyQuote = {
  total: number;
  payNow: number;
  discount: number;
  membershipDiscount: number;
  membership: { active: true; plan: { name: string; slug: string }; lines: MoneyLine[]; savedBdt: number; pointsMultiplier: number; bonusPoints: number } | null;
  wallet: { balanceBdt: number; appliedBdt: number } | null;
  walletApplied: number;
  emi: EmiPlan | null;
  emiCharge: number;
  emiAvailable: boolean;
  pointsToEarn: number;
  unboxing?: { available: boolean; selected: boolean; feeBdt: number; per: string; unitBdt: number; waived: boolean } | null;
  unboxingFee?: number;
  bundleDiscount?: number;
  /** coupon part of the discount (server rules: store, minimum, per customer, first order) */
  itemDiscount?: number;
  shipDiscount?: number;
};
export type QuoteOptions = { coupon?: string; redeemPoints?: number; useWallet?: boolean; payment?: string; emiTenure?: number; emiBank?: string; unboxing?: boolean };
type QuoteItem = { productId: string; variant?: string; qty: number; unitBdt: number };

/** Same maths as App\Services\Money\EmiService (and apps/web/src/lib/money.ts). Reasons are returned as keys. */
export function emiPlan(cfg: EmiConfig, amount: number, tenure: number, bank?: string | null): EmiPlan & { reasonKey?: "emi.off" | "emi.min" | "emi.chooseTenure" | "emi.noTenure" } {
  const base: EmiPlan = { eligible: false, reason: null, tenure, bank: bank ?? null, bankName: null, ratePct: 0, chargeBdt: 0, totalBdt: Math.round(amount), monthlyBdt: 0 };
  if (!cfg.enabled) return { ...base, reasonKey: "emi.off" };
  if (amount < cfg.minOrderBdt) return { ...base, reasonKey: "emi.min" };
  if (!cfg.tenures.includes(tenure)) return { ...base, reasonKey: "emi.chooseTenure" };
  const offering = cfg.banks.filter((b) => b.rates[String(tenure)] !== undefined);
  const b = bank ? cfg.banks.find((x) => x.code === bank) : [...offering].sort((x, y) => x.rates[String(tenure)] - y.rates[String(tenure)])[0];
  const rate = b ? b.rates[String(tenure)] : 0;
  if (b && rate === undefined) return { ...base, bankName: b.name, reasonKey: "emi.noTenure" };
  const charge = Math.ceil((amount * rate) / 100);
  const total = Math.round(amount) + charge;
  return { eligible: true, reason: null, tenure, bank: b?.code ?? null, bankName: b?.name ?? null, ratePct: rate, chargeBdt: charge, totalBdt: total, monthlyBdt: Math.ceil(total / tenure) };
}

let emiCache: Promise<EmiConfig> | null = null;

export const RETURN_PLUS = "skydropretail://plus/success";

export const moneyApi = {
  /** GET /emi (public). Cached for the session; demo mode: EMI off. */
  emiConfig: (): Promise<EmiConfig> => {
    if (isDemo()) return Promise.resolve({ enabled: false, minOrderBdt: 5000, tenures: [], banks: [] });
    emiCache ??= http<EmiConfig>("GET", "/emi").catch((e) => {
      emiCache = null;
      throw e;
    });
    return emiCache;
  },
  /** POST /checkout/quote with the money options (wallet, EMI, unboxing). Live API only. */
  quote: (items: QuoteItem[], o: QuoteOptions) =>
    http<MoneyQuote>("POST", "/checkout/quote", {
      body: { items, ...o, coupon: o.coupon || undefined, redeemPoints: o.redeemPoints || undefined, useWallet: o.useWallet || undefined, unboxing: o.unboxing || undefined },
    }),
  wallet: (): Promise<Wallet> => (isDemo() ? Promise.resolve({ balanceBdt: 0, expiringBdt: 0, expiringAt: null, history: [] }) : http<Wallet>("GET", "/wallet")),
  redeem: (code: string) => http<{ amountBdt: number; balanceBdt: number }>("POST", "/gift-cards/redeem", { body: { code } }),
  plans: (): Promise<{ items: PlusPlan[] }> => (isDemo() ? Promise.resolve({ items: DEMO_PLANS }) : http<{ items: PlusPlan[] }>("GET", "/membership/plans")),
  membership: () => http<{ membership: PlusMembership | null; savings: PlusSavings }>("GET", "/membership"),
  buyPlus: (plan: string, payment: "bkash" | "nagad" | "card") =>
    http<{ order: { id: string }; payment: { status: string; redirectUrl?: string } }>("POST", "/membership", { body: { plan, payment, returnUrl: RETURN_PLUS } }),
};

export function formatGiftCode(raw: string) {
  return raw.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 16).replace(/(.{4})(?=.)/g, "$1-");
}
