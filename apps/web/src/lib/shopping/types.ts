// Shopping module types (price history, compare stores, bundles, group buy, pre-orders, product requests).
// Shapes match docs/API.md "Shopping".
import type { MarketId, Product } from "../types";

export type PriceInsight = { type: "lowest" | "drop"; label: string; days?: number; dropBdt?: number };

export type PricePoint = { date: string; bdt: number; inr: number; inStock: boolean };

export type PriceHistory = {
  productId: string;
  days: number;
  points: PricePoint[];
  lowestBdt: number;
  highestBdt: number;
  averageBdt: number;
  currentBdt: number;
  /** 0 = at the lowest price of the period, 1 = at the highest */
  position: number;
  positionLabel: "lowest" | "low" | "typical" | "high" | "steady";
  badges: PriceInsight[];
  demo?: boolean;
};

export type CompareRow = {
  productId: string;
  market: MarketId;
  priceBdt: number;
  inStock: boolean;
  delivery: { min: number; max: number };
  current: boolean;
  best: boolean;
  match: { method: "gtin" | "model" | "title"; score: number } | null;
  product: Product;
};

export type CompareResult = { productId: string; items: CompareRow[]; bestId: string | null; savingBdt: number; demo?: boolean };

export type BoughtTogether = { product: Product; source: "copurchase" | "category"; items: (Product & { boughtTogether?: number })[]; totalBdt: number };

export type Bundle = {
  id: string;
  slug: string;
  title: string;
  description?: string | null;
  image?: string | null;
  discountPct: number;
  regularBdt: number;
  discountBdt: number;
  totalBdt: number;
  available: boolean;
  endsAt?: string | null;
  count: number;
  items?: { qty: number; product: Product }[];
};

export type Alternative = {
  productId: string;
  variant?: string | null;
  qty: number;
  title: string;
  market: MarketId;
  unitBdt: number;
  alternativeId: string;
  alternativeMarket: MarketId;
  alternativeBdt: number;
  savingBdt: number;
  delivery: { min: number; max: number };
  alternative: Product;
};

export type PreorderInfo = {
  productId: string;
  inStock: boolean;
  available: boolean;
  waitlist: number | null;
  watching: boolean;
  depositPct?: number;
  priceBdt?: number;
  depositBdt?: number;
  etaDays?: number;
  etaDate?: string;
  note?: string | null;
};

export type GroupMember = { name: string; isOwner: boolean; isYou: boolean; items: number; weightKg: number; savingBdt: number; status: string };

export type GroupItem = { productId: string; variant?: string | null; qty: number };

export type Group = {
  id: string;
  code: string;
  market: MarketId;
  status: "open" | "closing" | "closed" | "cancelled";
  closesAt: string;
  closedAt?: string | null;
  closeNote?: string | null;
  minMembers: number;
  owner: string;
  isOwner: boolean;
  shareUrl: string;
  members: GroupMember[];
  totals: { soloShippingBdt: number; groupShippingBdt: number; savingBdt: number; eachSavesBdt: number; weightKg: number; grouped: boolean };
  you: {
    items: GroupItem[];
    addressId: string | null;
    payment: string | null;
    status: string;
    orderId: string | null;
    error: string | null;
    savingBdt: number;
    shareBdt: number | null;
    soloShippingBdt: number | null;
  } | null;
  demo?: boolean;
};

export type RequestStatus = "new" | "sourcing" | "quoted" | "ordered" | "declined";

export type ProductRequest = {
  id: string;
  status: RequestStatus;
  url?: string | null;
  description?: string | null;
  photoUrl?: string | null;
  qty: number;
  budgetBdt?: number | null;
  market?: MarketId | null;
  productId?: string | null;
  quoteBdt?: number | null;
  quoteNote?: string | null;
  orderId?: string | null;
  payUrl?: string | null;
  quotedAt?: string | null;
  createdAt: string;
  demo?: boolean;
};
