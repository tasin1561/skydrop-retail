import type { Metadata } from "next";
import Link from "next/link";
import { offersFeed } from "@/lib/data";
import { isStoreMode, STORES, STORE_MODES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";

export const metadata: Metadata = { title: "Live deals and offers" };

type SP = Promise<Record<string, string | string[] | undefined>>;

export default async function DealsPage({ searchParams }: { searchParams: SP }) {
  const raw = (await searchParams).store;
  const store = isStoreMode(Array.isArray(raw) ? raw[0] : raw) ? ((Array.isArray(raw) ? raw[0] : raw) as keyof typeof STORES) : "all";
  const items = await offersFeed(store, 60);
  return (
    <div data-store={store} className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <section
        className="relative overflow-hidden rounded-[24px] p-5 text-white shadow-[var(--glow)] sm:p-7"
        style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
      >
        <span aria-hidden className="float absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/15" />
        <p className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
          <Icon name="bolt" size={14} stroke={2.2} /> Live deals
        </p>
        <h1 className="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Every offer from the Indian stores, in one place</h1>
        <p className="mt-1 max-w-xl text-sm text-white/85">
          Coupons, lightning deals, special prices and bank offers, checked when you view a product and again before you pay.
        </p>
      </section>

      <nav aria-label="Stores" className="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1">
        {STORE_MODES.map((m) => (
          <Link
            key={m}
            href={m === "all" ? "/deals" : `/deals?store=${m}`}
            data-store={m}
            aria-current={m === store ? "page" : undefined}
            className={
              "inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold transition " +
              (m === store ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-ink-2 shadow-[var(--shadow-1)] hover:text-accent")
            }
          >
            <Icon name={STORES[m].icon} size={15} stroke={2} />
            {m === "all" ? "All stores" : STORES[m].name}
          </Link>
        ))}
      </nav>

      <div className="mt-4">
        {items.length ? (
          <EnabledGrid items={items} />
        ) : (
          <p className="card p-8 text-center text-sm text-ink-2">No live deals right now. New deals arrive every few hours.</p>
        )}
      </div>
    </div>
  );
}
