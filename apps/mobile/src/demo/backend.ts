// Local stand-in for the Laravel API (docs/API.md), used when EXPO_PUBLIC_API_URL is unset or
// unreachable. Responses have the same shapes as the real endpoints so screens don't care.
import AsyncStorage from "@react-native-async-storage/async-storage";
import data from "./catalog.json";
import categoryTree from "./category-tree.json";
import categoryMap from "./category-map.json";
import { PRICING_GROUPS, filterTree, type CatNode } from "@/lib/categories";
import type { Address, CartItem, MarketId, Order, Product, StoreMode, User } from "@/lib/types";
import { MARKET_IDS, STORES } from "@/lib/markets";
import { priceBdt } from "@/lib/pricing";
import { computeTotals, findCoupon } from "@/lib/cart";
import { demoLedger } from "@/lib/points";
import { STAGES, currentStatus, DEMO_STAGE_MS } from "@/lib/orderStatus";
import type {
  CheckoutItem,
  Marketplace,
  OrderRequest,
  QuoteResponse,
  SearchParams,
  SearchResponse,
  Suggestion,
  VerifyResponse,
  PointsResponse,
  WatchType,
} from "@/api/types";

const ALL = (data as unknown as { products: Product[] }).products;
const wait = (ms: number) => new Promise((r) => setTimeout(r, ms));

const K = {
  addresses: "sr:demo:addresses",
  wishlist: "sr:demo:wishlist",
  orders: "sr:demo:orders",
  user: "sr:demo:user",
  watches: "sr:demo:watches",
};

async function read<T>(key: string, fallback: T): Promise<T> {
  try {
    const raw = await AsyncStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}
async function write(key: string, value: unknown) {
  try {
    await AsyncStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* storage full or unavailable: demo only */
  }
}

// ---------- catalog (same rules as apps/web/src/lib/catalog.ts) ----------
function score(p: Product, words: string[]) {
  if (!words.length) return 1;
  const hay = (p.title + " " + p.brand + " " + p.category).toLowerCase();
  let s = 0;
  for (const w of words) {
    if (hay.includes(w)) s += p.title.toLowerCase().startsWith(w) ? 3 : 1;
    else return 0;
  }
  return s;
}

export function search(sp: SearchParams): SearchResponse {
  const words = (sp.q ?? "")
    .toLowerCase()
    .split(/\s+/)
    .filter((w) => w.length > 1);
  let list = ALL.map((p) => ({ p, s: score(p, words) })).filter((x) => x.s > 0);
  if (sp.store && sp.store !== "all") list = list.filter((x) => x.p.market === sp.store);
  if (sp.category) list = list.filter((x) => inCategory(x.p, sp.category!));
  if (sp.min) list = list.filter((x) => priceBdt(x.p) >= sp.min!);
  if (sp.max) list = list.filter((x) => priceBdt(x.p) <= sp.max!);
  if (sp.rating) list = list.filter((x) => x.p.rating >= sp.rating!);
  if (sp.fast) list = list.filter((x) => x.p.prime);
  // Brand facets ignore the brand filter itself (same as the API), so shoppers can switch brands.
  const brands = brandFacets(list.map((x) => x.p));
  if (sp.brand?.length) {
    const want = new Set(sp.brand.map((b) => b.toLowerCase()));
    list = list.filter((x) => want.has((x.p.brand ?? "").trim().toLowerCase()));
  }
  const disc = (p: Product) => (p.mrpInr > p.priceInr ? 1 - p.priceInr / p.mrpInr : 0);
  switch (sp.sort) {
    case "price_asc":
      list.sort((a, b) => a.p.priceInr - b.p.priceInr);
      break;
    case "price_desc":
      list.sort((a, b) => b.p.priceInr - a.p.priceInr);
      break;
    case "rating":
      list.sort((a, b) => b.p.rating - a.p.rating || b.p.reviews - a.p.reviews);
      break;
    case "discount":
      list.sort((a, b) => disc(b.p) - disc(a.p));
      break;
    default:
      list.sort((a, b) => b.s - a.s || b.p.reviews - a.p.reviews);
  }
  const perPage = sp.perPage ?? 20;
  const page = Math.max(1, sp.page ?? 1);
  const total = list.length;
  return {
    status: "ready",
    total,
    page,
    pages: Math.max(1, Math.ceil(total / perPage)),
    items: list.slice((page - 1) * perPage, page * perPage).map((x) => x.p),
    facets: { brands },
  };
}

/** Top 20 brands (most products first), counts capped like the API. */
function brandFacets(items: Product[]): { name: string; count: number }[] {
  const m = new Map<string, { name: string; count: number }>();
  for (const p of items) {
    const name = (p.brand ?? "").trim();
    if (!name || name.length > 60) continue;
    const k = name.toLowerCase();
    const f = m.get(k);
    if (f) f.count++;
    else m.set(k, { name, count: 1 });
  }
  return [...m.entries()]
    .sort(([ka, a], [kb, b]) => b.count - a.count || (ka < kb ? -1 : ka > kb ? 1 : 0))
    .slice(0, 20)
    .map(([, f]) => ({ name: f.name, count: Math.min(f.count, 500) }));
}

export function suggest(q: string, store: StoreMode): Suggestion[] {
  return search({ q, store, perPage: 6 }).items.map((p) => ({ id: p.id, title: p.title, market: p.market as MarketId }));
}

export function product(id: string): Product | null {
  return ALL.find((p) => p.id === id) ?? null;
}

export function products(ids: string[]): Product[] {
  return ids.map(product).filter((p): p is Product => !!p);
}

export function rail(m: StoreMode, kind: "popular" | "deals", category?: string, limit = 12): Product[] {
  const base = ALL.filter((p) => (m === "all" || p.market === m) && (!category || p.category === category));
  if (kind === "deals")
    return base
      .filter((p) => p.mrpInr > p.priceInr)
      .sort((a, b) => b.mrpInr / b.priceInr - a.mrpInr / a.priceInr)
      .slice(0, limit);
  return base.sort((a, b) => b.reviews - a.reviews).slice(0, limit);
}

export function related(p: Product, limit = 8) {
  return ALL.filter((x) => x.id !== p.id && x.category === p.category && x.market === p.market)
    .sort((a, b) => b.reviews - a.reviews)
    .slice(0, limit);
}

/** Live deals feed: products with any offer first, then the deepest discounts (as apps/web/src/lib/data.ts). */
export function offers(store: StoreMode, limit = 40): Product[] {
  const withOffers = search({ store, perPage: 400 }).items.filter((p) => (p.offers?.length ?? 0) > 0);
  const discounted = rail(store, "deals", undefined, limit);
  const seen = new Set<string>();
  return [...withOffers, ...discounted].filter((p) => (seen.has(p.id) ? false : (seen.add(p.id), true))).slice(0, limit);
}

export function marketplaces(): Marketplace[] {
  return MARKET_IDS.map((id) => ({ id, name: STORES[id].name, source: STORES[id].source, enabled: true }));
}

// ---------- checkout ----------
export async function verify(items: CheckoutItem[]): Promise<VerifyResponse> {
  await wait(1100);
  const results = items.map((i) => {
    const p = product(i.productId);
    if (!p) return { productId: i.productId, ok: false, reason: "not_found" };
    const fresh = priceBdt(p);
    return { productId: i.productId, ok: true, freshBdt: fresh, changed: fresh !== i.unitBdt, deliverable: true };
  });
  return { ok: results.every((r) => r.ok), checkedAt: new Date().toISOString(), results };
}

function toCart(items: CheckoutItem[]): CartItem[] {
  return items.map((i) => {
    const p = product(i.productId);
    return {
      productId: i.productId,
      market: (p?.market ?? "amazon") as MarketId,
      title: p?.title ?? i.productId,
      image: p?.image ?? null,
      placeholder: p?.placeholder,
      variant: i.variant,
      unitBdt: i.unitBdt,
      weightKg: i.weightKg ?? 0.5,
      qty: i.qty,
    };
  });
}

export function quote(items: CheckoutItem[], coupon?: string, redeemPoints = 0): QuoteResponse {
  const t = computeTotals(toCart(items), findCoupon(coupon), redeemPoints);
  return {
    groups: t.groups.map((g) => ({ market: g.market, subtotal: g.sub, shipping: g.shipping })),
    subtotal: t.subtotal,
    shipping: t.shipping,
    discount: t.discount,
    total: t.total,
    coupon: findCoupon(coupon)?.code,
  };
}

// ---------- auth ----------
export async function otpRequest(_phone: string) {
  await wait(700);
  return { ok: true as const, expiresIn: 300, demoCode: "123456" };
}

export async function otpVerify(phone: string, code: string, name?: string) {
  await wait(800);
  if (!/^\d{6}$/.test(code)) throw new Error("Enter the 6-digit code");
  const existing = await read<User | null>(K.user, null);
  const isNew = !existing || existing.phone !== phone;
  const user: User = isNew ? { id: "demo-user", name: name || "Skydrop shopper", phone } : { ...existing!, name: name || existing!.name };
  await write(K.user, user);
  return { token: "demo-token-" + Date.now(), user, isNew };
}

export async function me() {
  return read<User | null>(K.user, null);
}

export async function updateMe(patch: Partial<User>) {
  const u = (await me()) ?? { name: "", phone: "" };
  const next = { ...u, ...patch };
  await write(K.user, next);
  return next;
}

// ---------- addresses ----------
export async function addresses() {
  return read<Address[]>(K.addresses, []);
}

export async function saveAddress(a: Omit<Address, "id"> & { id?: string }) {
  let list = await addresses();
  const id = a.id ?? "a" + Date.now().toString(36);
  const next: Address = { ...a, id, isDefault: a.isDefault || list.length === 0 };
  if (next.isDefault) list = list.map((x) => ({ ...x, isDefault: false }));
  const at = list.findIndex((x) => x.id === id);
  if (at >= 0) list[at] = next;
  else list.push(next);
  await write(K.addresses, list);
  return next;
}

export async function deleteAddress(id: string) {
  let list = (await addresses()).filter((a) => a.id !== id);
  if (list.length && !list.some((a) => a.isDefault)) list = list.map((a, i) => ({ ...a, isDefault: i === 0 }));
  await write(K.addresses, list);
}

// ---------- wishlist ----------
export async function wishlistIds() {
  return read<string[]>(K.wishlist, []);
}
export async function setWishlistIds(ids: string[]) {
  await write(K.wishlist, ids);
}

// ---------- orders ----------
function withSimulatedStatus(o: Order): Order {
  const status = currentStatus(o, Date.now());
  const created = Date.parse(o.createdAt);
  const upto = STAGES.findIndex((s) => s.status === status);
  const timeline =
    status === "cancelled"
      ? o.timeline
      : STAGES.slice(0, upto + 1).map((s, i) => ({ status: s.status, at: new Date(created + i * DEMO_STAGE_MS).toISOString() }));
  return { ...o, status, timeline };
}

export async function orders() {
  const list = await read<Order[]>(K.orders, []);
  return list.map(withSimulatedStatus).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function order(id: string) {
  return (await orders()).find((o) => o.id === id) ?? null;
}

export async function placeOrder(req: OrderRequest, address: Address) {
  await wait(1400);
  const q = quote(req.items, req.coupon, req.redeemPoints ?? 0);
  const t = computeTotals(toCart(req.items), findCoupon(req.coupon), req.redeemPoints ?? 0);
  const id = "SR-" + Math.random().toString(36).slice(2, 8).toUpperCase();
  const now = new Date().toISOString();
  const o: Order = {
    id,
    createdAt: now,
    items: toCart(req.items),
    address,
    payment: req.payment,
    paymentStatus: "pending",
    subtotal: q.subtotal,
    shipping: q.shipping,
    discount: q.discount,
    total: q.total,
    status: "placed",
    trackingCode: "SKD" + Math.floor(1e8 + Math.random() * 9e8),
    timeline: [{ status: "placed", at: now }],
    pointsRedeemed: t.pointsRedeemed,
    pointsDiscount: t.pointsDiscount,
    pointsEarned: t.pointsToEarn,
  };
  const list = await read<Order[]>(K.orders, []);
  await write(K.orders, [o, ...list]);
  return { order: o, payment: { status: "pending" as const } };
}

export async function cancelOrder(id: string) {
  const list = await read<Order[]>(K.orders, []);
  const next = list.map((o) => (o.id === id ? { ...o, status: "cancelled" as const } : o));
  await write(K.orders, next);
  return next.find((o) => o.id === id) ?? null;
}

// ---------- points ----------
export async function points(): Promise<PointsResponse> {
  const user = await me();
  const list = await orders();
  // Welcome bonus once signed in; the date is unknown in demo, so it sorts last.
  const l = demoLedger(list, user ? "1970-01-01T00:00:00.000Z" : undefined);
  return { balance: l.balance, pending: l.pending, history: l.history };
}

// ---------- watches (price drop / back in stock) ----------
export async function watchIds() {
  return read<string[]>(K.watches, []);
}
export async function setWatch(productId: string, on: boolean, _type?: WatchType) {
  const list = (await watchIds()).filter((x) => x !== productId);
  await write(K.watches, on ? [...list, productId] : list);
}

export async function signOut() {
  await AsyncStorage.removeItem(K.user).catch(() => {});
}

// ---------- category tree (demo) ----------
const CAT_TREE = (categoryTree as unknown as { tree: CatNode[] }).tree;
const CAT_MAP = categoryMap as Record<string, string>;

/** Tree path (with descendants) or one of the five pricing groups, like GET /search?category=. */
function inCategory(p: Product, category: string) {
  if (PRICING_GROUPS.includes(category)) return p.category === category;
  const path = CAT_MAP[p.id];
  return !!path && (path === category || path.startsWith(category + "/"));
}

function withCounts(nodes: CatNode[], store: StoreMode): CatNode[] {
  return nodes.map((n) => ({
    ...n,
    count: ALL.filter((p) => (store === "all" || p.market === store) && inCategory(p, n.path)).length,
    children: withCounts(n.children, store),
  }));
}

export function categories(store: StoreMode): CatNode[] {
  return withCounts(filterTree(CAT_TREE, store), store);
}
