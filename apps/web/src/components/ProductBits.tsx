import Link from "next/link";
import type { MarketId, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, formatBdt, mrpBdt, priceBdt } from "@/lib/pricing";
import { Icon } from "./Icon";
import { WishButton } from "./WishButton";
import { Countdown } from "./offers/Countdown";
import { headlineDeal } from "./offers/offerMeta";

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
    // Amazon CDN images are already sized; plain img keeps the demo free of an image proxy.
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={url} alt={p.title} loading="lazy" decoding="async" className={"object-contain " + (className ?? "")} />;
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
  return (
    <span className="inline-flex items-center gap-1 text-xs text-ink-2" aria-label={`${rating} out of 5 stars`}>
      <span className="inline-flex text-amber-500">
        {[1, 2, 3, 4, 5].map((i) => (
          <Icon key={i} name="star" size={size} stroke={1.4} fill={i <= full ? "currentColor" : i - 0.5 === full ? "url(#half)" : "none"} />
        ))}
      </span>
      <span className="font-semibold text-ink">{rating ? rating.toFixed(1) : "New"}</span>
      {reviews ? <span>({reviews >= 1000 ? (reviews / 1000).toFixed(1) + "k" : reviews})</span> : null}
    </span>
  );
}

export function OriginTag({ market, compact }: { market: MarketId; compact?: boolean }) {
  return (
    <span
      data-store={market}
      className="inline-flex w-fit items-center gap-1 self-start rounded-full px-2 py-0.5 text-[11px] font-semibold"
      style={{ background: "var(--accent-soft)", color: "var(--accent)" }}
      title={`Bought for you from ${STORES[market].source}, India`}
    >
      <Icon name="pin" size={12} stroke={2} />
      {compact ? STORES[market].source : `From ${STORES[market].source}`}
    </span>
  );
}

export function PriceLine({ p, big }: { p: Product; big?: boolean }) {
  const d = discountPct(p);
  return (
    <div className="flex flex-wrap items-baseline gap-x-2">
      <span className={(big ? "text-3xl" : "text-lg") + " font-extrabold tracking-tight text-ink"}>{formatBdt(priceBdt(p))}</span>
      {d > 0 && (
        <>
          <span className={(big ? "text-base" : "text-xs") + " text-ink-3 line-through"}>{formatBdt(mrpBdt(p))}</span>
          <span className={(big ? "text-base" : "text-xs") + " font-bold text-good"}>{d}% off</span>
        </>
      )}
    </div>
  );
}

export function ProductCard({ p, i = 0 }: { p: Product; i?: number }) {
  const deal = headlineDeal(p);
  const offerCount = p.offers?.length ?? 0;
  return (
    <article
      data-store={p.market}
      className="rise group relative flex flex-col overflow-hidden rounded-2xl bg-surface shadow-[var(--shadow-1)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-2)]"
      style={{ ["--i" as string]: i % 12 }}
    >
      <Link href={`/p/${p.id}`} className="flex flex-1 flex-col" prefetch={false}>
        <div className="relative aspect-square bg-white p-3">
          <ProductImage p={p} className="h-full w-full transition-transform duration-500 group-hover:scale-[1.04]" />
          {discountPct(p) >= 10 && (
            <span className="absolute left-2 top-2 rounded-full bg-brand-2 px-2 py-0.5 text-[11px] font-bold text-white shadow">
              -{discountPct(p)}%
            </span>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-1.5 p-3">
          <OriginTag market={p.market} compact />
          <h3 className="line-clamp-2 text-[13px] font-medium leading-snug text-ink sm:text-sm">{p.title}</h3>
          <Stars rating={p.rating} reviews={p.reviews} size={12} />
          {deal && (
            <span className="inline-flex w-fit items-center gap-1 rounded-md bg-bad px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-white">
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
              <p className="mt-0.5 text-[11px] font-bold text-good">₹{Math.round(p.couponInr)} coupon already applied</p>
            ) : offerCount > 0 ? (
              <p className="mt-0.5 flex items-center gap-1 text-[11px] font-bold text-accent">
                <Icon name="tag" size={12} stroke={2.2} /> {offerCount} {offerCount === 1 ? "offer" : "offers"} on the source store
              </p>
            ) : null}
            <p className="mt-0.5 flex items-center gap-1 text-[11px] text-ink-2">
              <Icon name="truck" size={13} /> Home delivery in 7-12 days
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
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
      {items.map((p, i) => (
        <ProductCard key={p.id} p={p} i={i} />
      ))}
    </div>
  );
}

export function Rail({ title, href, items, note }: { title: string; href?: string; items: Product[]; note?: string }) {
  if (!items.length) return null;
  return (
    <section className="mt-8">
      <div className="mb-3 flex items-end justify-between gap-3 px-4 sm:px-0">
        <div>
          <h2 className="text-lg font-extrabold tracking-tight sm:text-xl">{title}</h2>
          {note && <p className="text-xs text-ink-2 sm:text-sm">{note}</p>}
        </div>
        {href && (
          <Link href={href} className="group inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-accent">
            See all <Icon name="right" size={16} className="transition-transform group-hover:translate-x-0.5" />
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
