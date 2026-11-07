import type { MarketId, StoreMode } from "./types";

export type StoreInfo = {
  id: StoreMode;
  name: string;
  short: string;
  source: string; // shown to shoppers: where the product really comes from
  tagline: string;
  icon: string;
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
    icon: "globe",
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
    name: "Mega Store",
    short: "Mega",
    source: "amazon.in",
    tagline: "Electronics, beauty and everything else, sourced from amazon.in",
    icon: "bolt",
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
    name: "Style Bazaar",
    short: "Style",
    source: "flipkart.com",
    tagline: "Fashion, gadgets and home, sourced from flipkart.com",
    icon: "spark",
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
    name: "Daily Basket",
    short: "Basket",
    source: "bigbasket.com",
    tagline: "Indian groceries and kitchen staples, sourced from bigbasket.com",
    icon: "basket",
    heroTitle: "The Indian pantry you miss, delivered to Bangladesh",
    heroText: "Rice, tea, spices, snacks and more from bigbasket.com, packed and shipped by Skydrop.",
    categories: [
      { slug: "grocery", label: "Staples & Snacks", icon: "grain" },
      { slug: "home", label: "Household", icon: "home" },
      { slug: "beauty", label: "Personal care", icon: "beauty" },
    ],
  },
};

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
