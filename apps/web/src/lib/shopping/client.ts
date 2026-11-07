// Browser-side calls for the shopping features. API mode (NEXT_PUBLIC_API_URL) talks to Laravel; demo mode
// computes the same shapes locally (seeded price history, /api/shopping route, localStorage for groups and requests).
import { apiEnabled, apiRequest } from "../apiClient";
import { priceBdt, shippingFor } from "../pricing";
import type { CartItem, MarketId, Product } from "../types";
import { demoHistory, seeded } from "./history";
import type { Alternative, Group, GroupItem, PreorderInfo, PriceHistory, ProductRequest } from "./types";

const enc = encodeURIComponent;

/** Rejects after `ms` so optional sections never hang on a slow API (callers show an empty/retry state). */
export function withTimeout<T>(p: Promise<T>, ms = 8000): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const t = setTimeout(() => reject(new Error("timeout")), ms);
    p.then(
      (v) => {
        clearTimeout(t);
        resolve(v);
      },
      (e) => {
        clearTimeout(t);
        reject(e);
      },
    );
  });
}

function read<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}
function write(key: string, v: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(v));
  } catch {
    /* storage unavailable */
  }
}

async function local<T>(action: string, body: unknown): Promise<T> {
  const res = await fetch("/api/shopping", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ action, ...(body as object) }) });
  if (!res.ok) throw new Error("Request failed");
  return (await res.json()) as T;
}

// ---------------- price history ----------------

export type HistoryInput = Pick<Product, "id" | "priceInr" | "category" | "effectivePriceInr">;

export async function fetchPriceHistory(p: HistoryInput, days: number): Promise<PriceHistory> {
  if (!apiEnabled) return demoHistory(p, days);
  // Through our own server: the API only answers price history to the Next.js server (anti-scraping guard).
  const res = await withTimeout(fetch(`/api/price-history/${enc(p.id)}?days=${days}`, { headers: { Accept: "application/json" } }));
  if (!res.ok) throw new Error("price history " + res.status);
  return (await res.json()) as PriceHistory;
}

// ---------------- compare stores at checkout ----------------

export async function cheaperListings(items: CartItem[]): Promise<Alternative[]> {
  const body = { items: items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty })) };
  if (apiEnabled) return (await withTimeout(apiRequest.post<{ items: Alternative[] }>("/checkout/alternatives", body))).items;
  return (await withTimeout(local<{ items: Alternative[] }>("alternatives", body))).items;
}

// ---------------- bundles in the cart ----------------

export type BundleSaving = { discount: number; bundles: { id: string; slug: string; title: string; sets: number; discountBdt: number }[] };

export async function bundleSaving(items: CartItem[]): Promise<BundleSaving> {
  const body = { items: items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt })) };
  if (apiEnabled) {
    const q = await withTimeout(apiRequest.post<{ bundleDiscount?: number; bundles?: BundleSaving["bundles"] }>("/checkout/quote", body));
    return { discount: q.bundleDiscount ?? 0, bundles: q.bundles ?? [] };
  }
  return withTimeout(local<BundleSaving>("bundle-saving", body));
}

// ---------------- pre-orders and the restock waitlist ----------------

const DEMO_DEPOSIT = 30;
const DEMO_ETA = 21;

export async function preorderInfo(p: HistoryInput & { stock?: string }, inStock: boolean): Promise<PreorderInfo> {
  if (apiEnabled) return withTimeout(apiRequest.call<PreorderInfo>(`/products/${enc(p.id)}/preorder`));
  const price = priceBdt(p);
  const waitlist = 3 + Math.floor(seeded("wl:" + p.id)() * 48);
  return {
    productId: p.id,
    inStock,
    available: !inStock,
    waitlist,
    watching: read<string[]>("skydrop-retail:watches", []).includes(p.id),
    ...(!inStock
      ? {
          depositPct: DEMO_DEPOSIT,
          priceBdt: price,
          depositBdt: Math.ceil((price * DEMO_DEPOSIT) / 100),
          etaDays: DEMO_ETA,
          etaDate: new Date(Date.now() + DEMO_ETA * 86_400_000).toISOString().slice(0, 10),
        }
      : {}),
  };
}

export type PreorderInput = { productId: string; qty: number; addressId?: number; address?: unknown; payment: "bkash" | "nagad" | "card"; returnUrl: string };

export function placePreorder(input: PreorderInput) {
  return apiRequest.post<{ order: { id: string }; payment: { status: string; redirectUrl?: string; amount?: number } }>("/preorders", input);
}

// ---------------- product requests ----------------

const REQ_KEY = "skydrop-retail:requests";

export type RequestInput = {
  url?: string;
  description?: string;
  photoUrl?: string;
  qty?: number;
  budgetBdt?: number;
  name?: string;
  phone?: string;
  query?: string;
  source?: "web" | "search" | "header";
};

export async function createRequest(input: RequestInput): Promise<ProductRequest> {
  if (apiEnabled) return (await apiRequest.post<{ request: ProductRequest }>("/product-requests", input)).request;
  const r: ProductRequest = {
    id: "D" + Date.now().toString(36).toUpperCase(),
    status: "new",
    url: input.url ?? null,
    description: input.description ?? null,
    photoUrl: input.photoUrl ?? null,
    qty: input.qty ?? 1,
    budgetBdt: input.budgetBdt ?? null,
    createdAt: new Date().toISOString(),
    demo: true,
  };
  write(REQ_KEY, [r, ...read<ProductRequest[]>(REQ_KEY, [])].slice(0, 50));
  return r;
}

export async function myRequests(): Promise<ProductRequest[]> {
  if (apiEnabled) return (await withTimeout(apiRequest.call<{ items: ProductRequest[] }>("/product-requests"))).items;
  return read<ProductRequest[]>(REQ_KEY, []);
}

// ---------------- group buy ----------------

const GROUP_KEY = "skydrop-retail:groups";

type DemoGroup = { code: string; market: MarketId; closesAt: string; createdAt: string; status: Group["status"]; members: { name: string; you: boolean; items: CartItem[] }[] };

function demoView(g: DemoGroup, origin: string): Group {
  const grouped = g.members.filter((m) => m.items.length).length >= 2;
  const rows = g.members.map((m) => {
    const solo = m.items.length ? shippingFor(m.items) : 0;
    const w = m.items.reduce((s, i) => s + i.weightKg * i.qty, 0);
    return { m, solo, w };
  });
  const allItems = g.members.flatMap((m) => m.items);
  const groupShip = allItems.length ? shippingFor(allItems) : 0;
  const totalW = rows.reduce((s, r) => s + r.w, 0);
  const share = (r: (typeof rows)[number]) => (grouped ? Math.round(totalW > 0 ? (groupShip * r.w) / totalW : groupShip / rows.length) : r.solo);
  const saving = rows.reduce((s, r) => s + Math.max(0, r.solo - share(r)), 0);
  const you = rows.find((r) => r.m.you);
  const expired = Date.parse(g.closesAt) <= Date.now();
  return {
    id: g.code,
    code: g.code,
    market: g.market,
    status: g.status === "open" && expired ? "closed" : g.status,
    closesAt: g.closesAt,
    minMembers: 2,
    owner: g.members[0]?.name ?? "You",
    isOwner: true,
    shareUrl: `${origin}/group/${g.code}`,
    members: rows.map((r, i) => ({ name: r.m.name, isOwner: i === 0, isYou: r.m.you, items: r.m.items.reduce((s, x) => s + x.qty, 0), weightKg: r.w, savingBdt: Math.max(0, r.solo - share(r)), status: "joined" })),
    totals: {
      soloShippingBdt: rows.reduce((s, r) => s + r.solo, 0),
      groupShippingBdt: grouped ? groupShip : rows.reduce((s, r) => s + r.solo, 0),
      savingBdt: saving,
      eachSavesBdt: rows.length ? Math.floor(saving / rows.length) : 0,
      weightKg: totalW,
      grouped,
    },
    you: you
      ? { items: you.m.items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty })), addressId: null, payment: null, status: "joined", orderId: null, error: null, savingBdt: Math.max(0, you.solo - share(you)), shareBdt: share(you), soloShippingBdt: you.solo }
      : null,
    demo: true,
  };
}

const origin = () => (typeof window === "undefined" ? "" : window.location.origin);

export type GroupInput = { items?: GroupItem[]; addressId?: number; address?: unknown; payment?: string };

export const groups = {
  async create(market: MarketId, hours: number, input: GroupInput, cartItems: CartItem[], you = "You"): Promise<Group> {
    if (apiEnabled) return (await apiRequest.post<{ group: Group }>("/groups", { market, hours, ...input })).group;
    const code = Array.from({ length: 8 }, (_, i) => "ABCDEFGHJKMNPQRSTUVWXYZ23456789"[Math.floor(seeded(Date.now() + ":" + i)() * 31)]).join("");
    // A friend is simulated in demo mode, so the shipping split and savings are visible.
    const friend = cartItems.length ? [{ name: "Demo friend", you: false, items: cartItems.slice(0, 1).map((i) => ({ ...i, qty: 1 })) }] : [];
    const g: DemoGroup = {
      code,
      market,
      closesAt: new Date(Date.now() + hours * 3_600_000).toISOString(),
      createdAt: new Date().toISOString(),
      status: "open",
      members: [{ name: you, you: true, items: cartItems }, ...friend],
    };
    write(GROUP_KEY, [g, ...read<DemoGroup[]>(GROUP_KEY, [])].slice(0, 20));
    return demoView(g, origin());
  },
  async get(code: string): Promise<Group | null> {
    if (apiEnabled) {
      try {
        return (await withTimeout(apiRequest.call<{ group: Group }>(`/groups/${enc(code)}`))).group;
      } catch {
        return null;
      }
    }
    const g = read<DemoGroup[]>(GROUP_KEY, []).find((x) => x.code === code.toUpperCase());
    return g ? demoView(g, origin()) : null;
  },
  async mine(): Promise<Group[]> {
    if (apiEnabled) return (await withTimeout(apiRequest.call<{ items: Group[] }>("/groups"))).items;
    return read<DemoGroup[]>(GROUP_KEY, []).map((g) => demoView(g, origin()));
  },
  async join(code: string, input: GroupInput, cartItems: CartItem[]): Promise<Group> {
    if (apiEnabled) return (await apiRequest.post<{ group: Group }>(`/groups/${enc(code)}/join`, input)).group;
    return updateDemo(code, (g) => {
      const me = g.members.find((m) => m.you);
      if (me) me.items = cartItems;
      else g.members.push({ name: "You", you: true, items: cartItems });
    });
  },
  async leave(code: string): Promise<Group> {
    if (apiEnabled) return (await apiRequest.post<{ group: Group }>(`/groups/${enc(code)}/leave`, {})).group;
    return updateDemo(code, (g) => {
      g.members = g.members.filter((m) => !m.you || g.members.indexOf(m) === 0);
    });
  },
  async close(code: string): Promise<Group> {
    if (apiEnabled) return (await apiRequest.post<{ group: Group }>(`/groups/${enc(code)}/close`, {})).group;
    return updateDemo(code, (g) => {
      g.status = "closed";
    });
  },
  async cancel(code: string): Promise<Group> {
    if (apiEnabled) return (await apiRequest.post<{ group: Group }>(`/groups/${enc(code)}/cancel`, {})).group;
    return updateDemo(code, (g) => {
      g.status = "cancelled";
    });
  },
};

function updateDemo(code: string, fn: (g: DemoGroup) => void): Group {
  const list = read<DemoGroup[]>(GROUP_KEY, []);
  const g = list.find((x) => x.code === code.toUpperCase());
  if (!g) throw new Error("Group not found");
  fn(g);
  write(GROUP_KEY, list);
  return demoView(g, origin());
}

/** Shipping saving if `extra` items joined `mine` in one shipment (used for "start a group" previews). */
export function groupSavingPreview(mine: CartItem[], friendKg = 0.5): number {
  if (!mine.length) return 0;
  const avgUnit = mine.reduce((s, i) => s + i.unitBdt, 0) / mine.length;
  const friend: CartItem = { ...mine[0], productId: "friend", qty: 1, weightKg: friendKg, unitBdt: Math.round(avgUnit) };
  const solo = shippingFor(mine) + shippingFor([friend]);
  const together = shippingFor([...mine, friend]);
  return Math.max(0, Math.floor((solo - together) / 2));
}

