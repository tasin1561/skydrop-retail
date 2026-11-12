// Category types and tiny helpers with no data imports, so client components can use them without pulling the
// bundled demo catalog and tree (lib/categories.ts) into the browser bundle.
import type { MarketId, Product } from "./types";

export const PRICING_GROUPS = ["electronics", "fashion", "beauty", "grocery", "home"] as const;

/** One node as the API returns it (GET /categories); `count` is the visible product count of the subtree. */
export type CatNode = {
  path: string;
  slug?: string;
  name: string;
  nameBn?: string | null;
  icon: string;
  image?: string | null;
  /** up to 4 photos (admin image first, then the category's best products); empty = show the icon */
  images?: string[] | null;
  depth: number;
  featured?: boolean;
  stores: MarketId[];
  count?: number;
  children: CatNode[];
};

/** Category name in the shopper's language (nameBn in Bangla mode). Shared by server and client. */
export const catLabel = (n: { name: string; nameBn?: string | null }, bn: boolean) => (bn && n.nameBn ? n.nameBn : n.name);

export type CatCrumb = { path: string; name: string; nameBn?: string | null };

export type CategoryPageData = {
  node: CatNode;
  breadcrumbs: CatCrumb[];
  children: CatNode[];
  siblings: CatNode[];
  brands: { name: string; count: number }[];
  priceRange: { minBdt: number; maxBdt: number } | null;
  available: boolean;
};
