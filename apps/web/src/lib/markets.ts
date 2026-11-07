import type { MarketId, StoreMode } from "./types";
import type { MsgKey, TFn } from "@/i18n/core";

export type StoreInfo = {
  id: StoreMode;
  name: string;
  short: string;
  source: string; // shown to shoppers: where the product really comes from
  tagline: string;
  heroTitle: string;
  heroText: string;
  categories: { slug: string; label: string; icon: string }[];
};

// Original store identities for Skydrop Retail. Source domains are named in plain text only,
// so shoppers always know where an item is bought; no third-party logos or trade dress.
export const STORES: Record<StoreMode, StoreInfo> = {
  all: {
    id: "all",
    name: "All Stores",
    short: "All",
    source: "Every connected Indian store",
    tagline: "India's biggest stores, delivered to Bangladesh",
    heroTitle: "Shop India. Delivered to your door in Bangladesh.",
    heroText: "Search every connected Indian store at once. Prices in BDT, delivered by Skydrop.",
    categories: [
      { slug: "electronics", label: "Electronics", icon: "phone" },
      { slug: "fashion", label: "Fashion", icon: "shirt" },
      { slug: "beauty", label: "Beauty", icon: "beauty" },
      { slug: "grocery", label: "Grocery", icon: "grain" },
      { slug: "home", label: "Home", icon: "home" },
    ],
  },
  amazon: {
    id: "amazon",
    name: "Amazon",
    short: "Amazon",
    source: "amazon.in",
    tagline: "Electronics, beauty and everything else, sourced from amazon.in",
    heroTitle: "Millions of products from amazon.in, priced in taka",
    heroText: "Phones, audio, beauty and daily needs. We buy in India and Skydrop brings it home.",
    categories: [
      { slug: "electronics", label: "Electronics", icon: "phone" },
      { slug: "beauty", label: "Beauty", icon: "beauty" },
      { slug: "fashion", label: "Shoes", icon: "shoe" },
      { slug: "grocery", label: "Drinks & Grocery", icon: "juice" },
    ],
  },
  flipkart: {
    id: "flipkart",
    name: "Flipkart",
    short: "Flipkart",
    source: "flipkart.com",
    tagline: "Fashion, gadgets and home, sourced from flipkart.com",
    heroTitle: "India's trending fashion and gadgets, now in Bangladesh",
    heroText: "Big brands and big offers from flipkart.com, with Skydrop delivery to your district.",
    categories: [
      { slug: "fashion", label: "Fashion", icon: "shirt" },
      { slug: "electronics", label: "Gadgets", icon: "phone" },
      { slug: "home", label: "Home", icon: "home" },
      { slug: "beauty", label: "Beauty", icon: "beauty" },
    ],
  },
  bigbasket: {
    id: "bigbasket",
    name: "BigBasket",
    short: "BigBasket",
    source: "bigbasket.com",
    tagline: "Indian groceries and kitchen staples, sourced from bigbasket.com",
    heroTitle: "The Indian pantry you miss, delivered to Bangladesh",
    heroText: "Rice, tea, spices, snacks and more from bigbasket.com, packed and shipped by Skydrop.",
    categories: [
      { slug: "grocery", label: "Staples & Snacks", icon: "grain" },
      { slug: "home", label: "Household", icon: "home" },
      { slug: "beauty", label: "Personal care", icon: "beauty" },
    ],
  },
};

const CAT_KEYS: Record<string, MsgKey> = {
  Electronics: "store.cat.electronics", Fashion: "store.cat.fashion", Beauty: "store.cat.beauty", Grocery: "store.cat.grocery",
  Home: "store.cat.home", Shoes: "store.cat.shoes", "Drinks & Grocery": "store.cat.drinks", Gadgets: "store.cat.gadgets",
  "Staples & Snacks": "store.cat.staples", Household: "store.cat.household", "Personal care": "store.cat.personal",
};

/** STORES[m] in the shopper's language. Store names (Amazon, Flipkart, BigBasket) and source domains stay in English; shown as plain text, never logos. */
export function storeText(m: StoreMode, t: TFn): StoreInfo {
  const s = STORES[m];
  return {
    ...s,
    name: m === "all" ? t("store.all.name") : s.name,
    short: m === "all" ? t("store.all.short") : s.short,
    source: m === "all" ? t("store.all.source") : s.source,
    tagline: t(`store.${m}.tagline` as MsgKey),
    heroTitle: t(`store.${m}.heroTitle` as MsgKey),
    heroText: t(`store.${m}.heroText` as MsgKey),
    categories: s.categories.map((c) => ({ ...c, label: CAT_KEYS[c.label] ? t(CAT_KEYS[c.label]) : c.label })),
  };
}

export const MARKET_IDS: MarketId[] = ["amazon", "flipkart", "bigbasket"];
export const STORE_MODES: StoreMode[] = ["all", "amazon", "flipkart", "bigbasket"];

export function isMarket(x: string): x is MarketId {
  return (MARKET_IDS as string[]).includes(x);
}

export function isStoreMode(x: string | null | undefined): x is StoreMode {
  return !!x && (STORE_MODES as string[]).includes(x);
}

// Default on/off state. In production this comes from the admin panel (marketplaces table).
export const DEFAULT_ENABLED: Record<MarketId, boolean> = {
  amazon: true,
  flipkart: true,
  bigbasket: true,
};
