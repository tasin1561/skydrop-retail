import type { MetadataRoute } from "next";
import data from "@/data/catalog.json";
import { MARKET_IDS } from "@/lib/markets";
import { allCategoryPaths } from "@/lib/categories";

const BASE = "https://retail.skydrop.online";

export default function sitemap(): MetadataRoute.Sitemap {
  const products = (data as { products: { id: string }[] }).products;
  return [
    { url: BASE, changeFrequency: "daily", priority: 1 },
    ...MARKET_IDS.map((m) => ({ url: `${BASE}/store/${m}`, changeFrequency: "daily" as const, priority: 0.9 })),
    { url: `${BASE}/categories`, priority: 0.6 },
    ...allCategoryPaths().map((path) => ({ url: `${BASE}/c/${path}`, changeFrequency: "daily" as const, priority: path.includes("/") ? 0.6 : 0.8 })),
    { url: `${BASE}/how-it-works`, priority: 0.5 },
    { url: `${BASE}/calculator`, priority: 0.5 },
    ...["/help", "/contact", "/legal/terms", "/legal/privacy", "/legal/refunds", "/legal/shipping"].map((path) => ({
      url: `${BASE}${path}`,
      changeFrequency: "monthly" as const,
      priority: path.startsWith("/legal") ? 0.3 : 0.5,
    })),
    ...products.map((p) => ({ url: `${BASE}/p/${p.id}`, changeFrequency: "weekly" as const, priority: 0.7 })),
  ];
}
