"use client";

import Link from "next/link";
import type { MarketId, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, mrpBdt, priceBdt } from "@/lib/pricing";
import { useT } from "@/i18n/client";
import { Icon } from "./Icon";
import { WishButton } from "./WishButton";
import { Countdown } from "./offers/Countdown";
import { headlineDeal } from "./offers/offerMeta";
import { CardNotices } from "./cms/CardNotices";
import { PriceInsightChip } from "./shopping/PriceInsightChip";
import { FxImg, HoverImage } from "./motion/FxImg";

export function ProductImage({
  p,
  src,
  className,
  sizes,
}: {
  p: Pick<Product, "title" | "placeholder" | "image">;
  src?: string | null;
  className?: string;
  sizes?: "sm" | "lg";
}) {
  const url = src ?? p.image;
  if (url) {
    // Amazon CDN images are already sized; FxImg fades them in once decoded (blur-up stand-in).
    return <FxImg src={url} alt={p.title} loading="lazy" decoding="async" className={"object-contain " + (className ?? "")} />;
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

/** Amazon cards lead with a couple of short specs when the listing carries them. */
function keySpecs(p: Product): string[] {
  const out: string[] = [];
  for (const [k, v] of Object.entries(p.specs ?? {})) {
    if (!v || v.length > 22 || /brand|manufacturer|asin|model|rank|review|date|country|weight|dimension/i.test(k)) continue;
    out.push(v);
    if (out.length === 2) break;
  }
  return out;
}

export function ProductCard({ p, i = 0 }: { p: Product; i?: number }) {
  const offerCount = p.offers?.length ?? 0;
  const { t, digits, inr } = useT();
  const deal = headlineDeal(p, t);
  // Each store mode has its own card personality: dense deals (Amazon), editorial fashion (Flipkart, Myntra).
  const fashion = p.market === "flipkart" || p.market === "myntra";
  const sizes = fashion ? (p.variants ?? []).map((v) => v.label).filter(Boolean).slice(0, 4) : [];
  const specs = !fashion ? keySpecs(p) : [];
  return (
    <article
      data-store={p.market}
      data-i={i}
      className="mo-press group relative flex flex-col overflow-hidden rounded-xl bg-surface shadow-[var(--shadow-1)] hover:-translate-y-0.5 hover:shadow-[var(--shadow-2)]"
    >
      <Link href={`/p/${p.id}`} className="flex flex-1 flex-col" prefetch={false}>
        <div className={"mo-media overflow-hidden bg-white " + (fashion ? "aspect-[4/5] p-2" : "aspect-square p-3")}>
          <ProductImage p={p} className="h-full w-full" />
          {p.images?.[1] && p.images[1] !== p.image && <HoverImage src={p.images[1]} />}
          {discountPct(p) >= 10 && (
            <span className="absolute left-2 top-2 rounded bg-bad px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-white">
              -{digits(discountPct(p))}%
            </span>
          )}
          {p.bestSeller && !fashion && (
            <span className="absolute bottom-2 left-2 rounded bg-ink px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-bg">
              {t("card.bestSeller")}
            </span>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-1.5 p-3">
          <OriginTag market={p.market} compact />
          {fashion && p.brand && <p className="text-[11px] font-semibold uppercase tracking-wide text-ink">{p.brand}</p>}
          <h3 className={"line-clamp-2 leading-snug text-ink " + (fashion ? "text-[13px] font-normal" : "text-[13px] font-medium sm:text-sm")}>{p.title}</h3>
          {specs.length > 0 && <p className="truncate text-[11px] text-ink-2">{specs.join(" · ")}</p>}
          {sizes.length > 0 && (
            <div className="flex flex-wrap gap-1" aria-label={t("card.sizes")}>
              {sizes.map((s) => (
                <span key={s} className="rounded border border-line px-1.5 py-0.5 text-[10px] font-medium text-ink-2">
                  {s}
                </span>
              ))}
            </div>
          )}
          <Stars rating={p.rating} reviews={p.reviews} size={12} />
          <CardNotices p={p} />
          <PriceInsightChip p={p} />
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
          <div className="mt-auto pt-1">
            <PriceLine p={p} />
            {p.couponInr ? (
              <p className="mt-0.5 text-[11px] font-semibold text-good">{t("card.couponApplied", { amount: inr(p.couponInr) })}</p>
            ) : offerCount > 0 ? (
              <p className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-ink-2">
                <Icon name="tag" size={12} stroke={2.2} /> {t("card.offers", { count: offerCount })}
              </p>
            ) : null}
            {p.market === "amazon" && p.salesVolume && (
              <p className="mt-1 flex items-center gap-1 text-[11px] font-medium text-ink-2">
                <Icon name="bolt" size={11} stroke={2.2} className="text-ink-3" /> {p.salesVolume}
              </p>
            )}
            <p className="mt-0.5 flex items-center gap-1 text-[11px] text-ink-2">
              <Icon name="truck" size={13} /> {t("card.delivery")}
            </p>
          </div>
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
  return (
    <section className="mt-8">
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
