// CMS data (home banners, flash sales). Uses the Laravel API (GET /cms/home) when API_URL is set,
// otherwise a demo built from the bundled catalog so the storefront always has content.
import * as local from "./catalog";
import { MARKET_IDS, STORES } from "./markets";
import { PRICING, priceBdt } from "./pricing";
import { checkRestriction } from "./restrictions";
import type { MarketId, Product, StoreMode } from "./types";

const API = process.env.API_URL?.replace(/\/$/, "");

export type Banner = {
  id: string;
  title: string;
  subtitle?: string | null;
  image?: string | null;
  /** "contain" for product shots on the demo banners; API banner images are full-bleed covers. */
  imageFit?: "cover" | "contain";
  link?: string | null;
  cta?: string | null;
  store: StoreMode;
  placement: "hero" | "strip" | "category";
  startsAt?: string | null;
  endsAt?: string | null;
};

export type FlashSaleItem = { product: Product; regularBdt: number; extraOffBdt: number; flashBdt: number };

export type FlashSale = {
  id: string;
  title: string;
  store: StoreMode;
  status: "live" | "upcoming";
  startsAt: string;
  endsAt: string;
  maxQty?: number | null;
  items: FlashSaleItem[];
};

export type CmsHome = { banners: Banner[]; flashSales: FlashSale[]; demo?: boolean };

export async function getCmsHome(store: StoreMode = "all"): Promise<CmsHome> {
  if (API) {
    try {
      const res = await fetch(`${API}/cms/home?store=${store}`, { headers: { Accept: "application/json" }, next: { revalidate: 60 } });
      if (res.ok) {
        const data = (await res.json()) as CmsHome;
        return { banners: data.banners ?? [], flashSales: (data.flashSales ?? []).filter((s) => s.items?.length) };
      }
    } catch {
      /* fall through to demo content */
    }
  }
  return demoHome(store);
}

// ---------------- demo content ----------------

const HOUR = 3_600_000;

/** Rolling 6-hour demo flash window, so the countdown always means something. */
function demoWindow(now = Date.now()) {
  const end = Math.ceil((now + 1) / (6 * HOUR)) * 6 * HOUR;
  return { startsAt: new Date(end - 6 * HOUR).toISOString(), endsAt: new Date(end).toISOString() };
}

let demoIds: Map<string, number> | null = null;
/** Demo sale: the three deepest deals per store, 5-8% extra off (capped at the margin, like the API). */
function demoSaleIds() {
  if (!demoIds) {
    demoIds = new Map();
    for (const m of MARKET_IDS) local.deals(m, 3).forEach((p, i) => demoIds!.set(p.id, [8, 6, 5][i] ?? 5));
  }
  return demoIds;
}

function demoOff(p: Product, pct: number) {
  const regular = priceBdt({ ...p, flashSale: null, restriction: null });
  const cap = Math.floor(regular - regular / (1 + PRICING.margin));
  return { regular, off: Math.min(cap, Math.round((regular * pct) / 100)) };
}

/** Demo mode: adds `restriction` (title rules) and the demo `flashSale` to a bundled product. */
export function demoDecorate<T extends Product | null>(p: T): T {
  if (!p || p.restriction !== undefined) return p;
  const restriction = checkRestriction(p.title);
  const pct = demoSaleIds().get(p.id);
  if (!pct) return { ...p, restriction };
  const { off } = demoOff(p, pct);
  return { ...p, restriction, flashSale: { saleId: "demo", title: "Flash hour", endsAt: demoWindow().endsAt, extraOffBdt: off, maxQty: 2 } };
}

function demoHome(store: StoreMode): CmsHome {
  const markets: MarketId[] = store === "all" ? MARKET_IDS : [store];
  const items: FlashSaleItem[] = [];
  for (const [id, pct] of demoSaleIds()) {
    const p = local.getProduct(id);
    if (!p || !markets.includes(p.market)) continue;
    const { regular, off } = demoOff(p, pct);
    items.push({ product: demoDecorate(p), regularBdt: regular, extraOffBdt: off, flashBdt: regular - off });
  }
  const w = demoWindow();
  const next = demoWindow(Date.parse(w.endsAt) + 1);
  const upcoming = markets.flatMap((m) => local.byMarket(m, 2)).filter((p) => !demoSaleIds().has(p.id));

  const shot = (m: MarketId, i = 0) => local.deals(m, 4)[i]?.image ?? local.byMarket(m, 1)[0]?.image ?? null;
  const promos: Banner[] = [
    { id: "demo-flash", title: "Flash hour: extra taka off, every six hours", subtitle: "A fresh set of deals with an extra Skydrop discount. Limited to 2 per order.", image: shot("amazon"), link: "#fs-demo-live", cta: "See flash deals", store: "amazon", placement: "hero" },
    { id: "demo-fashion", title: "New season kurtas, sarees and sneakers", subtitle: `${STORES.flipkart.name} brings ${STORES.flipkart.source} fashion to every district.`, image: shot("flipkart"), link: "/store/flipkart", cta: `Shop ${STORES.flipkart.name}`, store: "flipkart", placement: "hero" },
    { id: "demo-pantry", title: "Indian pantry, delivered", subtitle: "Spices, snacks and tea from bigbasket.com. Duty and customs already in the price.", image: shot("bigbasket"), link: "/store/bigbasket", cta: `Shop ${STORES.bigbasket.name}`, store: "bigbasket", placement: "hero" },
    // Demo only. Live coupon promos come from the admin (featured coupons, GET /coupons/featured).
    { id: "demo-coupon", title: "Save more with Skydrop coupons", subtitle: "Enter a coupon code in your cart or at checkout.", image: shot("amazon", 1), link: "/deals", cta: "Find a deal", store: "all", placement: "hero" },
  ];
  const banners: Banner[] = promos
    .filter((b) => store === "all" || b.store === "all" || b.store === store)
    .map((b) => ({ ...b, imageFit: "contain" as const }));

  return {
    demo: true,
    banners,
    flashSales: [
      { id: "demo-live", title: "Flash hour", store, status: "live" as const, ...w, maxQty: 2, items },
      {
        id: "demo-next",
        title: "Next flash",
        store,
        status: "upcoming" as const,
        ...next,
        maxQty: 2,
        items: upcoming.map((p) => {
          const { regular, off } = demoOff(p, 5);
          return { product: { ...p, restriction: checkRestriction(p.title) }, regularBdt: regular, extraOffBdt: off, flashBdt: regular - off };
        }),
      },
    ].filter((s) => s.items.length),
  };
}
