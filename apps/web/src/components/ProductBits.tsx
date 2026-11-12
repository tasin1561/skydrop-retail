"use client";

import Link from "next/link";
import type { MarketId, Product } from "@/lib/types";
import { DELIVERY_DAYS, STORES } from "@/lib/markets";
import { discountPct, mrpBdt, priceBdt } from "@/lib/pricing";
import { useT } from "@/i18n/client";
import { Icon } from "./Icon";
import { WishButton } from "./WishButton";
import { Countdown } from "./offers/Countdown";
import { headlineDeal } from "./offers/offerMeta";
import { CardNotices } from "./cms/CardNotices";
import { PriceInsightChip } from "./shopping/PriceInsightChip";
import { FxImg, HoverImage } from "./motion/FxImg";
import { StoreName } from "./store/StoreName";

export function ProductImage({
  p,
  src,
  className,
  sizes,
  w,
}: {
  p: Pick<Product, "title" | "placeholder" | "image">;
  src?: string | null;
  className?: string;
  sizes?: "sm" | "lg";
  /** shown width in CSS px, so the photo is fetched at that size (default: a product card) */
  w?: number;
}) {
  const url = src ?? p.image;
  if (url) {
    // Amazon CDN images are already sized; FxImg fades them in once decoded (blur-up stand-in).
    return <FxImg src={url} alt={p.title} w={w ?? (sizes === "lg" ? 480 : 240)} loading="lazy" decoding="async" className={"object-contain " + (className ?? "")} />;
  }
  const hue = p.placeholder?.hue ?? 210;
  return (
    <div
      role="img"
      aria-label={p.title}
      className={"grid place-items-center " + (className ?? "")}
      style={{
        background: `radial-gradient(circle at 30% 25%, hsl(${hue} 90% 92%), hsl(${hue} 70% 82%))`,
        color: `hsl(${hue} 60% 32%)`,
      }}
    >
      <Icon name={p.placeholder?.icon ?? "package"} size={sizes === "lg" ? 120 : 56} stroke={1.3} />
    </div>
  );
}

export function Stars({ rating, reviews, size = 14 }: { rating: number; reviews?: number; size?: number }) {
  const full = Math.round(rating * 2) / 2;
  const { t, digits } = useT();
  return (
    <span className="inline-flex items-center gap-1 text-xs text-ink-2" aria-label={t("card.stars", { rating: digits(rating) })}>
      <span className="inline-flex text-amber-500">
        {[1, 2, 3, 4, 5].map((i) => (
          <Icon key={i} name="star" size={size} stroke={1.4} fill={i <= full ? "currentColor" : i - 0.5 === full ? "url(#half)" : "none"} />
        ))}
      </span>
      <span className="font-semibold text-ink">{rating ? digits(rating.toFixed(1)) : t("card.new")}</span>
      {reviews ? <span>({digits(reviews >= 1000 ? (reviews / 1000).toFixed(1) + "k" : reviews)})</span> : null}
    </span>
  );
}

export function OriginTag({ market, compact }: { market: MarketId; compact?: boolean }) {
  const { t } = useT();
  return (
    <span
      data-store={market}
      className="inline-flex w-fit items-center gap-1 self-start text-[11px] font-medium text-ink-3"
      title={t("card.boughtFrom", { source: STORES[market].source })}
    >
      <Icon name="pin" size={12} stroke={2} />
      {compact ? STORES[market].source : t("card.from", { source: STORES[market].source })}
    </span>
  );
}

/** Price block that reads instantly: current price, struck MRP, % off (and savings on the big variant). */
export function PriceLine({ p, big }: { p: Product; big?: boolean }) {
  const d = discountPct(p);
  const { t, bdt } = useT();
  const save = mrpBdt(p) - priceBdt(p);
  return (
    <div className="tabular-nums">
      <div className="flex flex-wrap items-baseline gap-x-2">
        <span className={(big ? "text-3xl" : "text-lg") + " font-bold tracking-tight text-ink"}>{bdt(priceBdt(p))}</span>
        {d > 0 && (
          <>
            <span className={(big ? "text-base" : "text-xs") + " text-ink-3 line-through"}>{bdt(mrpBdt(p))}</span>
            <span className={(big ? "text-base" : "text-xs") + " font-semibold text-good"}>{t("card.off", { pct: d })}</span>
          </>
        )}
      </div>
      {big && d > 0 && save > 0 && <p className="mt-0.5 text-sm font-medium text-good">{t("card.save", { amount: bdt(save) })}</p>}
    </div>
  );
}

/** "20K+ bought in past month" (Amazon) → "20K+". */
function boughtCount(p: Product): string | null {
  const m = p.market === "amazon" ? /^([\d.,]+[kKmM]?\+?)/.exec((p.salesVolume ?? "").trim()) : null;
  return m ? m[1].toUpperCase() : null;
}

/**
 * Product card, built for a phone first: big photo, store badge on it, a two-line title, the taka price with what it
 * was, one line of rating and sales, one line of India → Bangladesh delivery days. Nothing else competes for the eye.
 */
export function ProductCard({ p, i = 0 }: { p: Product; i?: number }) {
  const { t, digits, inr, bdt } = useT();
  const found = headlineDeal(p, t);
  // A deal tag that only repeats the discount ("80% OFF", "Rs. 2023 OFF") adds noise next to the price: keep timed
  // deals and other kinds (lightning deal, special price, combo).
  const deal = found && (found.endsAt || !/(\d+\s*%|(rs\.?|₹)\s*[\d,]+)\s*off/i.test(found.label)) ? found : null;
  const fashion = p.market === "flipkart" || p.market === "myntra";
  const sizes = fashion ? (p.variants ?? []).map((v) => v.dimensions?.Size ?? v.label).filter(Boolean) : [];
  const shownSizes = Array.from(new Set(sizes)).slice(0, 4);
  const off = discountPct(p);
  const bought = boughtCount(p);
  const [dMin, dMax] = DELIVERY_DAYS[p.market] ?? [7, 12];
  return (
    <article
      data-store={p.market}
      data-i={i}
      className="mo-press group relative flex flex-col overflow-hidden rounded-2xl bg-surface shadow-[var(--shadow-1)] hover:-translate-y-0.5 hover:shadow-[var(--shadow-2)]"
    >
      <Link href={`/p/${p.id}`} className="flex flex-1 flex-col" prefetch={false}>
        <div className={"mo-media relative overflow-hidden bg-white " + (fashion ? "aspect-[4/5]" : "aspect-square p-3")}>
          <ProductImage p={p} className={"h-full w-full " + (fashion ? "object-cover" : "")} />
          {p.images?.[1] && p.images[1] !== p.image && <HoverImage src={p.images[1]} />}
          {off >= 5 && (
            <span className="absolute left-2 top-2 rounded-md bg-bad px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-white shadow-sm">
              -{digits(off)}%
            </span>
          )}
          <span className="absolute bottom-2 left-2 max-w-[80%] rounded-full bg-white">
            <StoreName mode={p.market} variant="pill" tone="light" size="text-[11px] px-2 py-0.5" />
          </span>
          {p.bestSeller && (
            <span className="absolute bottom-2 right-2 rounded-md bg-ink/85 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-bg">
              {t("card.bestSeller")}
            </span>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-1 p-2.5 sm:p-3">
          {fashion && p.brand && <p className="truncate text-[11px] font-bold uppercase tracking-wide text-ink-2">{p.brand}</p>}
          <h3 className="line-clamp-2 min-h-[2.5em] text-[13px] leading-snug text-ink sm:text-sm">{p.title}</h3>
          <div className="mt-0.5 tabular-nums">
            <div className="flex flex-wrap items-baseline gap-x-1.5">
              <span className="text-[17px] font-extrabold tracking-tight text-ink sm:text-lg">{bdt(priceBdt(p))}</span>
              {off > 0 && <span className="text-[11px] text-ink-3 line-through">{bdt(mrpBdt(p))}</span>}
              {off > 0 && <span className="text-[11px] font-bold text-good">{t("card.off", { pct: digits(off) })}</span>}
            </div>
            {p.couponInr ? <p className="text-[11px] font-semibold text-good">{t("card.couponApplied", { amount: inr(p.couponInr) })}</p> : null}
          </div>
          {deal && (
            <span className="inline-flex w-fit items-center gap-1 rounded bg-[color-mix(in_srgb,var(--bad)_10%,transparent)] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-bad">
              <Icon name="bolt" size={11} stroke={2.4} /> {deal.label}
              {deal.endsAt && (
                <>
                  {" · "}
                  <Countdown endsAt={deal.endsAt} compact />
                </>
              )}
            </span>
          )}
          {shownSizes.length > 1 && (
            <p className="truncate text-[11px] text-ink-2" aria-label={t("card.sizes")}>
              {shownSizes.join(" · ")}
            </p>
          )}
          <p className="flex items-center gap-1 text-[11px] text-ink-2">
            {p.rating > 0 ? (
              <span className="inline-flex items-center gap-0.5" aria-label={t("card.stars", { rating: digits(p.rating) })}>
                <Icon name="star" size={12} stroke={1.4} fill="currentColor" className="text-amber-500" />
                <span className="font-semibold text-ink">{digits(p.rating.toFixed(1))}</span>
                {p.reviews ? <span>({digits(p.reviews >= 1000 ? (p.reviews / 1000).toFixed(1) + "k" : p.reviews)})</span> : null}
              </span>
            ) : (
              <span className="font-semibold text-ink">{t("card.new")}</span>
            )}
            {bought && (
              <>
                <span aria-hidden className="text-ink-3">·</span>
                <span className="truncate">{t("card.bought", { count: bought })}</span>
              </>
            )}
          </p>
          <CardNotices p={p} />
          <PriceInsightChip p={p} />
          <p className="mt-auto flex items-center gap-1 pt-0.5 text-[11px] font-medium text-ink-2">
            <Icon name="truck" size={13} className="shrink-0" />
            <span className="truncate">{t("card.eta", { min: digits(dMin), max: digits(dMax) })}</span>
          </p>
        </div>
      </Link>
      <div className="absolute right-2 top-2">
        <WishButton id={p.id} small />
      </div>
    </article>
  );
}

export function ProductGrid({ items }: { items: Product[] }) {
  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5">
      {items.map((p, i) => (
        <ProductCard key={p.id} p={p} i={i} />
      ))}
    </div>
  );
}

export function Rail({ title, href, items, note }: { title: string; href?: string; items: Product[]; note?: string }) {
  const { t } = useT();
  if (!items.length) return null;
  // cv-auto: rows below the screen are not laid out or painted until the shopper scrolls near them (globals.css)
  return (
    <section className="cv-auto mt-8">
      <div className="mb-3 flex items-end justify-between gap-3 px-4 sm:px-0">
        <div>
          <h2 className="text-lg font-bold tracking-tight sm:text-xl">{title}</h2>
          {note && <p className="text-xs text-ink-2 sm:text-sm">{note}</p>}
        </div>
        {href && (
          <Link href={href} className="group -my-3 -mr-2 inline-flex min-h-11 shrink-0 items-center gap-1 px-2 text-sm font-semibold text-accent">
            {t("card.seeAll")} <Icon name="right" size={16} />
          </Link>
        )}
      </div>
      <div className="no-scrollbar snap-x-rail flex gap-3 overflow-x-auto px-4 pb-2 sm:px-0">
        {items.map((p, i) => (
          <div key={p.id} className="w-[44vw] max-w-[220px] shrink-0 sm:w-[200px]">
            <ProductCard p={p} i={i} />
          </div>
        ))}
      </div>
    </section>
  );
}
