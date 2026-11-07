import type { MetadataRoute } from "next";
import data from "@/data/catalog.json";
import { MARKET_IDS } from "@/lib/markets";

const BASE = "https://retail.skydrop.online";

export default function sitemap(): MetadataRoute.Sitemap {
  const products = (data as { products: { id: string }[] }).products;
  return [
    { url: BASE, changeFrequency: "daily", priority: 1 },
    ...MARKET_IDS.map((m) => ({ url: `${BASE}/store/${m}`, changeFrequency: "daily" as const, priority: 0.9 })),
    { url: `${BASE}/categories`, priority: 0.6 },
    { url: `${BASE}/how-it-works`, priority: 0.5 },
    ...products.map((p) => ({ url: `${BASE}/p/${p.id}`, changeFrequency: "weekly" as const, priority: 0.7 })),
  ];
}
