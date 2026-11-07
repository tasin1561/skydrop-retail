import "./product.css";
import Link from "next/link";
import type { Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, mrpBdt, priceBdt, weightKg } from "@/lib/pricing";
import { Icon } from "@/components/Icon";
import { OriginTag, PriceLine, Stars } from "@/components/ProductBits";
import { WishButton } from "@/components/WishButton";
import { Accordion } from "./Accordion";
import { BuyBox } from "./BuyBox";
import { DeliveryCard, isUnavailableStock, StockLine } from "./DeliveryCard";
import { Gallery } from "./Gallery";
import { PriceBreakdown } from "./PriceBreakdown";
import { ProductExtras } from "./ProductExtras";
import { OffersSection } from "../offers/OffersSection";
import { ReviewList, StarBars } from "./Reviews";
import { ShareButton } from "@/components/share/ShareButton";
import type { BuyInfo, Media } from "./types";
import { FlashNotice, isBlocked, RestrictionNotice } from "@/components/cms/ProductNotices";
import { ShopperReviews } from "@/components/cms/ShopperReviews";
import { AskAboutProduct } from "@/components/assistant/AskAssistant";
import { SafeSection } from "@/components/resilience/SafeSection";
import { ShoppingBuyExtras, ShoppingSections } from "@/components/shopping/ShoppingSections";

// Server component: shapes the product into small serialisable props for the client parts.
export function ProductDetail({
  p,
  knownIds,
  unavailableIds,
  children,
}: {
  p: Product;
  knownIds: string[];
  unavailableIds: string[];
  children?: React.ReactNode;
}) {
  const store = STORES[p.market];
  const source = store.source;
  const cat = store.categories.find((c) => c.slug === p.category) ?? STORES.all.categories.find((c) => c.slug === p.category);
  const catLabel = cat?.label ?? p.category;

  const images = p.images?.length ? p.images : p.image ? [p.image] : [];
  const media: Media[] = images.map((src) => ({ kind: "image", src }));
  // Videos go after the first photo so the hero is always a picture.
  const videos: Media[] = (p.videos ?? []).map((v) => ({ kind: "video", hls: v.hls, thumb: v.thumb }));
  media.splice(Math.min(1, media.length), 0, ...videos);

  const available = !isUnavailableStock(p.stock) && !p.demo;
  const info: BuyInfo = {
    id: p.id,
    market: p.market,
    source,
    title: p.title,
    image: p.image,
    placeholder: p.placeholder,
    unitBdt: priceBdt(p),
    mrpBdt: mrpBdt(p),
    discount: discountPct(p),
    weightKg: weightKg(p),
    variants: p.variants ?? [],
    available: !isUnavailableStock(p.stock) && !isBlocked(p),
  };

  const specs = Object.entries(p.specs ?? {}).filter(([k, v]) => v && !/customer reviews|best sellers rank/i.test(k));
  const bullets = (p.bullets ?? []).filter(Boolean);
  const reviews = p.reviewList ?? [];
  const breakdown = (p.starBreakdown ?? []).filter((r) => r.percentage >= 0);
  const aplus = p.aplus ?? [];

  const badges = (
    <>
      {discountPct(p) >= 10 && (
        <span className="rounded-full bg-brand-2 px-2.5 py-1 text-[11px] font-bold text-white shadow-[0_6px_16px_rgb(255_106_61/0.35)]">-{discountPct(p)}%</span>
      )}
      {p.bestSeller && (
        <span className="inline-flex items-center gap-1 rounded-full bg-surface/90 px-2.5 py-1 text-[11px] font-bold text-ink shadow-[var(--shadow-1)] backdrop-blur">
          <Icon name="spark" size={12} className="text-accent" /> Best seller
        </span>
      )}
    </>
  );

  return (
    <div data-store={p.market} className="mx-auto max-w-7xl pb-8 sm:px-6 lg:px-8">
      {/* breadcrumb */}
      <nav aria-label="Breadcrumb" className="px-4 pb-2 pt-3 sm:px-0 sm:pt-5">
        <ol className="no-scrollbar flex items-center gap-1 overflow-x-auto whitespace-nowrap text-xs text-ink-2 sm:text-sm">
          <li>
            <Link href="/" className="inline-flex min-h-[32px] items-center rounded-full px-1.5 hover:text-accent">
              Home
            </Link>
          </li>
          <li aria-hidden>
            <Icon name="right" size={14} className="text-ink-3" />
          </li>
          <li>
            <Link href={`/store/${p.market}`} className="inline-flex min-h-[32px] items-center gap-1 rounded-full px-1.5 hover:text-accent">
              <Icon name={store.icon} size={14} /> {store.name}
            </Link>
          </li>
          <li aria-hidden>
            <Icon name="right" size={14} className="text-ink-3" />
          </li>
          <li>
            <Link
              href={`/search?q=&store=${p.market}&category=${p.category}`}
              aria-current="page"
              className="inline-flex min-h-[32px] items-center rounded-full px-1.5 font-semibold text-ink hover:text-accent"
            >
              {catLabel}
            </Link>
          </li>
        </ol>
      </nav>

      <div className="grid grid-cols-1 gap-0 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-10">
        {/* gallery */}
        <div className="relative">
          <Gallery
            key={p.id}
            p={{ title: p.title, image: p.image, placeholder: p.placeholder }}
            market={p.market}
            media={media}
            source={source}
            hasVideosOnly={!!p.hasVideos && !videos.length}
            badges={badges}
          />
        </div>

        {/* info column */}
        <div className="relative px-4 pt-5 sm:px-0 lg:pt-0">
          {p.demo && (
            <p className="mb-3 flex items-start gap-2 rounded-2xl bg-[color-mix(in_srgb,var(--warn)_12%,transparent)] px-3 py-2.5 text-xs text-ink">
              <Icon name="info" size={16} className="mt-px shrink-0 text-warn" />
              <span>
                <span className="font-bold">Demo listing.</span> Real {source} products appear here once this store is connected. You can still try the
                cart and checkout flow with it.
              </span>
            </p>
          )}

          <div className="flex items-start gap-3">
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <OriginTag market={p.market} />
                {p.prime && (
                  <span className="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-semibold text-ink-2">
                    <Icon name="bolt" size={12} /> Fast dispatch in India
                  </span>
                )}
              </div>
              {p.brand && <p className="mt-2 text-sm font-semibold text-accent">{p.brand}</p>}
            </div>
            <div className="flex shrink-0 gap-2">
              <ShareButton title={p.title} path={`/p/${encodeURIComponent(p.id)}`} qr />
              <WishButton id={p.id} />
            </div>
          </div>

          <h1 className="mt-1 text-[19px] font-bold leading-snug tracking-tight text-ink sm:text-2xl">{p.title}</h1>

          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
            {p.rating > 0 ? (
              <a href="#source-reviews" className="rounded-full hover:opacity-80">
                <Stars rating={p.rating} reviews={p.reviews} />
              </a>
            ) : (
              <Stars rating={0} />
            )}
            {p.salesVolume && <span className="text-xs text-ink-2">{p.salesVolume} on {source}</span>}
          </div>

          <div className="mt-4">
            <PriceLine p={p} big />
            <p className="mt-0.5 text-xs text-ink-2">Includes import duty and service. Shipping by weight in cart.</p>
            <FlashNotice p={p} />
          </div>

          <div className="mt-3">
            <PriceBreakdown p={p} source={source} />
            <OffersSection p={p} />
            <ProductExtras id={p.id} priceBdt={priceBdt(p)} inStock={!isUnavailableStock(p.stock)} />
          </div>

          <div className="mt-4">
            <StockLine stock={p.stock} demo={p.demo} />
          </div>

          <RestrictionNotice p={p} />
          <SafeSection label="Assistant" quiet><div className="mt-3"><AskAboutProduct productId={p.id} title={p.title} /></div></SafeSection>

          <div className="mt-5">
            <BuyBox key={p.id} info={info} knownIds={knownIds} unavailableIds={unavailableIds} />
          </div>
          <ShoppingBuyExtras p={p} />

          {!available && !p.demo && (
            <p className="mt-3 text-xs text-bad" role="status">
              This item is out of stock on {source} right now. Save it to your wishlist and check back.
            </p>
          )}

          <ul className="mt-5 grid grid-cols-3 gap-2 text-center text-[11px] text-ink-2">
            {[
              { icon: "shield", t: "Genuine from India" },
              { icon: "refresh", t: "Price checked live" },
              { icon: "cash", t: "Cash on delivery" },
            ].map((x) => (
              <li key={x.t} className="flex flex-col items-center gap-1.5 rounded-2xl bg-surface px-2 py-3 shadow-[var(--shadow-1)]">
                <span className="chip-icon h-8 w-8 rounded-[10px]">
                  <Icon name={x.icon} size={16} />
                </span>
                <span className="font-semibold leading-tight text-ink">{x.t}</span>
              </li>
            ))}
          </ul>

          <div className="mt-5">
            <DeliveryCard source={source} kolkata={p.deliveryKolkata} weightKg={weightKg(p)} />
          </div>
        </div>
      </div>

      {/* details */}
      <div className="mt-8 grid gap-6 px-4 sm:px-0 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-10">
        <div className="flex flex-col gap-4">
          {bullets.length > 0 && (
            <section className="card p-4 sm:p-5" aria-labelledby="pd-about">
              <h2 id="pd-about" className="mb-3 flex items-center gap-3 text-lg font-extrabold tracking-tight">
                <span className="chip-icon">
                  <Icon name="info" size={18} />
                </span>
                About this item
              </h2>
              <ul className="flex flex-col gap-2.5">
                {bullets.map((b, i) => {
                  const m = b.match(/^\s*\[([^\]]+)\]\s*:?\s*([\s\S]*)$/) ?? b.match(/^([^:]{3,60}):\s+([\s\S]*)$/);
                  return (
                    <li key={i} className="flex gap-3 text-sm leading-relaxed text-ink-2">
                      <span className="mt-1 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] text-accent">
                        <Icon name="check" size={12} stroke={2.8} />
                      </span>
                      <span>
                        {m ? (
                          <>
                            <span className="font-semibold text-ink">{m[1].trim()}.</span> {m[2]}
                          </>
                        ) : (
                          b
                        )}
                      </span>
                    </li>
                  );
                })}
              </ul>
            </section>
          )}

          {specs.length > 0 && (
            <section className="card px-3 py-2 sm:px-4">
              <Accordion title="Specifications" icon="grid" meta={`${specs.length} details from ${source}`} defaultOpen={specs.length <= 8}>
                <dl className="overflow-hidden rounded-xl">
                  {specs.map(([k, v], i) => (
                    <div key={k} className={"grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2.5 text-sm " + (i % 2 ? "" : "bg-surface-2/70")}>
                      <dt className="font-medium text-ink-2">{k}</dt>
                      <dd className="break-words text-ink">{v}</dd>
                    </div>
                  ))}
                </dl>
              </Accordion>
            </section>
          )}

          {aplus.length > 0 && (
            <section className="card px-3 py-2 sm:px-4">
              <Accordion title="From the brand" icon="spark" meta={`${aplus.length} images from the listing on ${source}`} lazy>
                <div className="flex flex-col gap-2 overflow-hidden rounded-xl bg-white">
                  {aplus.map((src, i) => (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img key={i} src={src} alt={`${p.brand || "Brand"} feature image ${i + 1}`} loading="lazy" decoding="async" className="h-auto w-full" />
                  ))}
                </div>
              </Accordion>
            </section>
          )}
        </div>

        <div className="flex flex-col gap-4">
        {/* Our shoppers' reviews first; the source store's rating stays separate below. */}
        <ShopperReviews productId={p.id} source={source} sourceRating={p.rating} sourceCount={p.reviews} />
        {(breakdown.length > 0 || reviews.length > 0 || p.rating > 0) && (
          <section id="source-reviews" className="scroll-mt-24" aria-labelledby="pd-reviews">
            <div className="card p-4 sm:p-5">
              <h2 id="pd-reviews" className="flex items-center gap-3 text-lg font-extrabold tracking-tight">
                <span className="chip-icon">
                  <Icon name="star" size={18} />
                </span>
                Ratings on {source}
              </h2>
              <div className="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center">
                <div className="flex shrink-0 items-center gap-3 sm:flex-col sm:items-start sm:gap-1">
                  <span className="text-4xl font-extrabold tracking-tight">{p.rating ? p.rating.toFixed(1) : "-"}</span>
                  <div>
                    <Stars rating={p.rating} />
                    <p className="text-xs text-ink-2">{p.reviews.toLocaleString("en-IN")} ratings</p>
                  </div>
                </div>
                {breakdown.length > 0 && (
                  <div className="min-w-0 flex-1">
                    <StarBars rows={breakdown} />
                  </div>
                )}
              </div>
              <p className="mt-4 flex items-start gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs text-ink-2">
                <Icon name="globe" size={15} className="mt-px shrink-0 text-accent" />
                Ratings and reviews come from shoppers on {source}, India. They are shown as published there.
              </p>
            </div>
            {reviews.length > 0 && (
              <div className="mt-3">
                <ReviewList reviews={reviews} source={source} />
              </div>
            )}
          </section>
        )}
        </div>
      </div>

      <ShoppingSections p={p} />
      {children}
    </div>
  );
}
