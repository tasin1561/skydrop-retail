"use client";

import Link from "next/link";
import { useCallback } from "react";
import { useRouter } from "next/navigation";
import type { Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, mrpBdt, priceBdt } from "@/lib/pricing";
import { useT } from "@/i18n/client";
import { useStore } from "@/state/store";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import { headlineDeal } from "@/components/offers/offerMeta";
import { FlipCountdown } from "@/components/motion/FlipCountdown";

/** Next midnight in Dhaka (UTC+6, no DST), as epoch ms. */
function nextDhakaMidnight(now: number) {
  const day = 864e5;
  const off = 6 * 3.6e6;
  return Math.floor((now + off) / day) * day + day - off;
}

/**
 * Home "Deal of the day": one real discounted product from the live offers feed. The pick rotates
 * daily (Dhaka time, `now` comes from the server render) among the deepest discounts. The countdown is honest: a store deal's own end
 * time when it has one, otherwise the time until today's pick changes.
 */
export function DealOfTheDay({ items, now }: { items: Product[]; now: number }) {
  const day = Math.floor((now + 6 * 3.6e6) / 864e5);
  const { enabled } = useStore();
  const { t, bdt, digits } = useT();
  const router = useRouter();
  const onEnd = useCallback(() => router.refresh(), [router]);
  const pool = items
    .filter((p) => enabled[p.market] && discountPct(p) >= 5 && (p.image || p.placeholder))
    .sort((a, b) => discountPct(b) - discountPct(a))
    .slice(0, 5);
  if (!pool.length) return null;
  const p = pool[day % pool.length];
  const deal = headlineDeal(p, t);
  const dealEnd = deal?.endsAt ? Date.parse(deal.endsAt) : NaN;
  const ownEnd = Number.isFinite(dealEnd) && dealEnd > now + 60_000;
  const to = ownEnd ? dealEnd : nextDhakaMidnight(now);
  const d = discountPct(p);

  return (
    <section aria-labelledby="dd-h" className="mt-6 px-4 sm:px-0">
      <div data-store={p.market} className="dd-card grid gap-4 p-4 sm:grid-cols-[minmax(0,240px)_1fr] sm:items-center sm:gap-6 sm:p-6">
        <Link
          href={`/p/${p.id}`}
          prefetch={false}
          className="relative mx-auto grid aspect-square w-40 place-items-center rounded-[10px] bg-white p-4 ring-1 ring-line sm:w-full"
          tabIndex={-1}
          aria-hidden="true"
        >
          <ProductImage p={p} className="h-full w-full" />
          <span className="absolute left-2 top-2 grid place-items-center rounded bg-bad px-1.5 py-1 text-center text-[12px] font-semibold leading-none tabular-nums text-white">
            -{digits(d)}%
          </span>
        </Link>
        <div className="relative min-w-0">
          <p className="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-accent">
            <Icon name="bolt" size={13} stroke={2.4} /> {t("home.dealOfDay")}
          </p>
          <h2 id="dd-h" className="mt-2 line-clamp-2 text-lg font-bold leading-snug tracking-tight sm:text-xl">
            {p.title}
          </h2>
          <p className="mt-1 text-xs text-ink-2">
            {t("dd.from", { source: STORES[p.market].source })}{deal ? ` · ${deal.label}` : ""}
          </p>
          <div className="mt-3 flex flex-wrap items-baseline gap-x-2 tabular-nums">
            <span className="text-2xl font-bold tracking-tight">{bdt(priceBdt(p))}</span>
            <span className="text-sm text-ink-3 line-through">{bdt(mrpBdt(p))}</span>
            <span className="text-sm font-semibold text-good">{t("dd.save", { amount: bdt(mrpBdt(p) - priceBdt(p)) })}</span>
          </div>
          <div className="mt-4 flex flex-wrap items-end justify-between gap-4 sm:justify-start sm:gap-8">
            <FlipCountdown to={to} label={ownEnd ? t("dd.storeEnds") : t("dd.nextPick")} onEnd={onEnd} />
            <Link href={`/p/${p.id}`} prefetch={false} className="btn btn-primary px-5">
              {t("dd.see")} <Icon name="arrow" size={18} className="arrow" />
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
