import type { Metadata } from "next";
import Link from "next/link";
import { byMarket } from "@/lib/data";
import { MARKET_IDS, STORES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";

export const metadata: Metadata = { title: "Categories" };

export default async function CategoriesPage() {
  const samples = Object.fromEntries(
    await Promise.all(
      MARKET_IDS.flatMap((m) => STORES[m].categories.map(async (c) => [`${m}:${c.slug}`, (await byMarket(m, 1, c.slug))[0]] as const)),
    ),
  );
  return (
    <div className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <h1 className="text-2xl font-extrabold tracking-tight">Shop by category</h1>
      <p className="text-sm text-ink-2">Pick a store, then a category. Every item ships to Bangladesh with Skydrop.</p>
      <div className="mt-6 space-y-8">
        {MARKET_IDS.map((m) => {
          const s = STORES[m];
          return (
            <section key={m} data-store={m}>
              <div className="mb-3 flex items-center justify-between">
                <h2 className="flex items-center gap-2 text-lg font-extrabold">
                  <span className="chip-icon is-active">
                    <Icon name={s.icon} size={18} />
                  </span>
                  {s.name}
                  <span className="text-xs font-semibold text-ink-3">from {s.source}</span>
                </h2>
                <Link href={`/store/${m}`} className="text-sm font-semibold text-accent">
                  Visit store
                </Link>
              </div>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {s.categories.map((c, i) => {
                  const sample = samples[`${m}:${c.slug}`];
                  return (
                    <Link
                      key={c.slug}
                      href={`/search?q=&store=${m}&category=${c.slug}`}
                      className="card rise group flex items-center gap-3 p-3 transition hover:-translate-y-0.5 hover:shadow-[var(--shadow-2)]"
                      style={{ ["--i" as string]: i }}
                    >
                      <div className="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-white p-1">
                        {sample ? <ProductImage p={sample} className="h-full w-full" /> : <Icon name={c.icon} size={32} />}
                      </div>
                      <div className="min-w-0">
                        <p className="text-sm font-bold">{c.label}</p>
                        <p className="flex items-center gap-1 text-xs text-accent">
                          Browse <Icon name="right" size={12} className="transition-transform group-hover:translate-x-0.5" />
                        </p>
                      </div>
                    </Link>
                  );
                })}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
