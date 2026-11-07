import type { Metadata } from "next";
import Link from "next/link";
import { offersFeed } from "@/lib/data";
import { isStoreMode, STORES, STORE_MODES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";
import { getT } from "@/i18n/server";
import { StoreName } from "@/components/store/StoreName";

export async function generateMetadata(): Promise<Metadata> {
  return { title: (await getT()).t("deals.meta") };
}

type SP = Promise<Record<string, string | string[] | undefined>>;

export default async function DealsPage({ searchParams }: { searchParams: SP }) {
  const raw = (await searchParams).store;
  const store = isStoreMode(Array.isArray(raw) ? raw[0] : raw) ? ((Array.isArray(raw) ? raw[0] : raw) as keyof typeof STORES) : "all";
  const [items, { t }] = await Promise.all([offersFeed(store, 60), getT()]);
  return (
    <div data-store={store} className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <section
        className="relative overflow-hidden rounded-[16px] p-5 text-white shadow-[var(--glow)] sm:p-7"
        style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
      >
        <span aria-hidden className="float absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/15" />
        <p className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
          <Icon name="bolt" size={14} stroke={2.2} /> {t("deals.live")}
        </p>
        <h1 className="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{t("deals.title")}</h1>
        <p className="mt-1 max-w-xl text-sm text-white/85">
          {t("deals.sub")}
        </p>
      </section>

      <nav aria-label={t("deals.stores")} className="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1">
        {STORE_MODES.map((m) => (
          <Link
            key={m}
            href={m === "all" ? "/deals" : `/deals?store=${m}`}
            data-store={m}
            aria-current={m === store ? "page" : undefined}
            className={"sn inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm transition " + (m === store ? "shadow-[var(--glow)]" : "bg-surface shadow-[var(--shadow-1)]")}
            style={m === store ? { background: "var(--sn-fill)" } : undefined}
          >
            <StoreName mode={m} fill={m === store} name={m === "all" ? t("search.allStores") : STORES[m].name} />
          </Link>
        ))}
      </nav>

      <div className="mt-4">
        {items.length ? (
          <EnabledGrid items={items} />
        ) : (
          <div className="card flex flex-col items-center gap-4 p-8 text-center text-sm text-ink-2">
            <p>{t("deals.none")}</p>
            <Link href="/search" className="btn btn-ghost min-h-11">
              {t("cart.keepShopping")} <Icon name="arrow" size={16} className="arrow" />
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
