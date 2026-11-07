import type { Metadata } from "next";
import { getT } from "@/i18n/server";
import { notFound } from "next/navigation";
import { byMarket, loadProduct, related, usingApi } from "@/lib/data";
import { getProduct } from "@/lib/data";
import { STORES } from "@/lib/markets";
import { formatBdt, priceBdt } from "@/lib/pricing";
import { Rail } from "@/components/ProductBits";
import { ProductDetail } from "@/components/product/ProductDetail";
import { isUnavailableStock } from "@/components/product/DeliveryCard";
import { TrackView } from "@/components/tracking/TrackView";
import { LiveFetch } from "@/components/resilience/LiveFetch";
import { LiveRefresh, StaleNote } from "@/components/resilience/LiveRefresh";
import { ErrorState } from "@/components/resilience/ErrorState";
import { SafeSection } from "@/components/resilience/SafeSection";
import type { Product } from "@/lib/types";

type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const [r, { t, bdt }] = await Promise.all([loadProduct(decodeURIComponent(id)), getT()]);
  if (r.kind === "fetching") return { title: t("pd.metaLoading"), robots: { index: false } };
  if (r.kind !== "ready") return { title: t("pd.metaNotFound") };
  const p = r.product;
  const source = STORES[p.market].source;
  const title = p.title.length > 70 ? p.title.slice(0, 67).trimEnd() + "..." : p.title;
  const description = `${p.brand ? p.brand + " · " : ""}${t("pd.metaDesc", { price: bdt(priceBdt(p)), source })}`;
  const path = `/p/${encodeURIComponent(p.id)}`;
  // og:image comes from ./opengraph-image.tsx (product card with BDT price); X/Twitter falls back to it.
  return {
    metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "https://retail.skydrop.online"),
    title,
    description,
    alternates: { canonical: path },
    openGraph: { type: "website", siteName: "Skydrop Retail", url: path, title: p.title, description, locale: "en_BD" },
    twitter: { card: "summary_large_image", title, description },
  };
}

export default async function ProductPage({ params }: Props) {
  const { id: raw } = await params;
  const id = decodeURIComponent(raw);
  const [r, { t }] = await Promise.all([loadProduct(id), getT()]);

  if (r.kind === "notfound") notFound();
  if (r.kind === "unavailable") {
    return <ErrorState title={t("pd.unavailTitle")} body={t("pd.unavailBody")} />;
  }
  if (r.kind === "fetching") {
    // Not in our database yet: show the live-fetch screen with something to browse meanwhile.
    const browse = await byMarket(((r.market as Product["market"]) ?? "all") as never, 12).catch(() => [] as Product[]);
    return (
      <LiveFetch
        jobId={r.jobId}
        market={r.market}
        productId={id}
        browse={browse.length ? <Rail title={t("pd.whileWait")} note={t("pd.popularNow")} items={browse} /> : null}
      />
    );
  }

  const p = r.product;
  const store = STORES[p.market];

  // Variant availability. With the API, the product JSON already says which variants we know and which
  // are out of stock (no extra calls). In demo mode we look the siblings up in the bundled catalog.
  const knownIds: string[] = [];
  const unavailableIds: string[] = [];
  if (usingApi) {
    for (const v of p.variants ?? []) {
      const vv = v as { id?: string; available?: boolean };
      if (!vv.id) continue;
      knownIds.push(vv.id);
      if (vv.available === false) unavailableIds.push(vv.id);
    }
  } else {
    const ids = Array.from(new Set((p.variants ?? []).map((v) => v.id).filter((v): v is string => !!v && v !== p.id)));
    const others = await Promise.all(ids.slice(0, 60).map((vid) => getProduct(vid).catch(() => null)));
    for (const other of [p, ...others]) {
      if (!other || !(p.variants ?? []).some((v) => v.id === other.id)) continue;
      knownIds.push(other.id);
      if (isUnavailableStock(other.stock)) unavailableIds.push(other.id);
    }
  }

  const more = await related(p).catch(() => [] as Product[]);

  return (
    <>
      {r.stale && (
        <div className="mx-auto max-w-7xl px-4 pt-3">
          <StaleNote />
        </div>
      )}
      <ProductDetail p={p} knownIds={knownIds} unavailableIds={unavailableIds}>
        <TrackView product={{ id: p.id, title: p.title, brand: p.brand, category: p.category, priceBdt: priceBdt(p) }} />
        <SafeSection label={t("pd.similar")} quiet>
          <div className="mt-4">
            <Rail
              title={t("pd.moreLike")}
              note={t("pd.popularOn", { source: store.source })}
              href={`/search?q=&store=${p.market}&category=${p.category}`}
              items={more}
            />
          </div>
        </SafeSection>
      </ProductDetail>
      {r.refreshJobId && <LiveRefresh jobId={r.refreshJobId} source={store.source} />}
    </>
  );
}
