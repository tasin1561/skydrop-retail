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

export const RETURN_PLUS = "skydropretail://plus/success";

export const moneyApi = {
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
