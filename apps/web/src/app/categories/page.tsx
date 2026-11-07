import type { Metadata } from "next";
import Link from "next/link";
import { getCategoryTree } from "@/lib/data";
import { isStoreMode, MARKET_IDS, STORES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { CategoryExplorer } from "@/components/categories/CategoryExplorer";

export const metadata: Metadata = {
  title: "All categories",
  description: "Every category from amazon.in, Flipkart and BigBasket, delivered to Bangladesh by Skydrop.",
  alternates: { canonical: "/categories" },
};

type SP = Record<string, string | string[] | undefined>;

export default async function CategoriesPage({ searchParams }: { searchParams: Promise<SP> }) {
  const raw = (await searchParams).store;
  const first = Array.isArray(raw) ? raw[0] : raw;
  const store = isStoreMode(first) ? first : "all";
  const tree = await getCategoryTree("all");
  return (
    <div className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <h1 className="text-2xl font-extrabold tracking-tight">Shop by category</h1>
      <p className="text-sm text-ink-2">Everything the Indian stores sell, in one tree. Every item ships to Bangladesh with Skydrop.</p>
      <CategoryExplorer tree={tree} initialStore={store} />
      <section className="mt-10">
        <h2 className="mb-3 text-lg font-extrabold">Or browse a store</h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {MARKET_IDS.map((m) => (
            <Link key={m} href={`/store/${m}`} data-store={m} className="card cm-tile flex min-h-16 items-center gap-3 p-3">
              <span className="chip-icon is-active">
                <Icon name={STORES[m].icon} size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">{STORES[m].name}</span>
                <span className="block text-xs text-ink-3">from {STORES[m].source}</span>
              </span>
              <Icon name="right" size={16} className="text-ink-3" />
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
