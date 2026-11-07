import "./shopping.css";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import { formatBdt } from "@/lib/pricing";
import type { CompareResult } from "@/lib/shopping/types";
import { getT } from "@/i18n/server";

/** "Compare stores": the same product on other listings, with BDT price, delivery estimate and a Best price tag. */
export async function CompareStores({ data }: { data: CompareResult }) {
  if (data.items.length < 2) return null;
  const { t, bdt } = await getT();
  const current = data.items.find((r) => r.current);
  const best = data.items.find((r) => r.best);
  return (
    <section className="card mt-4 overflow-hidden" aria-labelledby="sh-compare-h">
      <div className="flex items-center justify-between gap-3 px-4 pb-2 pt-4">
        <h2 id="sh-compare-h" className="flex items-center gap-2.5 text-base font-extrabold tracking-tight">
          <span className="chip-icon h-8 w-8 rounded-[10px]">
            <Icon name="refresh" size={16} />
          </span>
          {t("cmp.title")}
        </h2>
        <span className="text-xs text-ink-2">{t("cmp.listings", { count: data.items.length })}</span>
      </div>
      {best && current && !current.best && data.savingBdt > 0 && (
        <p className="mx-4 mb-2 rounded-xl bg-[color-mix(in_srgb,var(--good)_12%,transparent)] px-3 py-2 text-xs font-semibold text-good">
          {t("cmp.save", { amount: bdt(data.savingBdt), store: STORES[best.market].name })}
        </p>
      )}
      <ul className="divide-y divide-line">
        {data.items.map((r, i) => {
          const store = STORES[r.market];
          const row = (
            <div className="flex min-h-[60px] items-center gap-3 px-4 py-2.5">
              <span data-store={r.market} className="chip-icon h-9 w-9 shrink-0 rounded-[10px]">
                <Icon name={store.icon} size={17} />
              </span>
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-1.5 text-sm font-bold text-ink">
                  {store.name}
                  {r.best && (
                    <span className="sh-chip rounded-full bg-good px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-white" style={{ animationDelay: `${i * 60}ms` }}>
{t("cmp.best")}
</span>
                  )}
                  {r.current && <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-bold text-ink-2">{t("cmp.viewing")}</span>}
                </p>
                <p className="truncate text-xs text-ink-2">
                  {store.source} · {r.inStock ? t("cmp.days", { min: r.delivery.min, max: r.delivery.max }) : t("stock.out")}
                </p>
              </div>
              <div className="text-right">
                <p className={"text-sm font-extrabold tabular-nums " + (r.best ? "text-good" : "text-ink")}>{bdt(r.priceBdt)}</p>
                {!r.current && current && r.priceBdt !== current.priceBdt && (
                  <p className="text-[11px] font-semibold tabular-nums text-ink-2">
                    {r.priceBdt < current.priceBdt ? "−" : "+"}
                    {bdt(Math.abs(r.priceBdt - current.priceBdt))}
                  </p>
                )}
              </div>
              {!r.current && <Icon name="right" size={16} className="shrink-0 text-ink-3" />}
            </div>
          );
          return (
            <li key={r.productId}>
              {r.current ? (
                <div aria-current="true" className="bg-surface-2/60">
                  {row}
                </div>
              ) : (
                <Link href={`/p/${encodeURIComponent(r.productId)}`} className="block transition-colors hover:bg-surface-2" aria-label={t("cmp.switch", { store: store.name, price: bdt(r.priceBdt) })}>
                  {row}
                </Link>
              )}
            </li>
          );
        })}
      </ul>
      <p className="px-4 pb-3 pt-2 text-[11px] text-ink-3">{t("cmp.note")}</p>
    </section>
  );
}
