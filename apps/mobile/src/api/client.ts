// Typed client for the Skydrop Retail API (docs/API.md).
// Base URL comes from EXPO_PUBLIC_API_URL. When it is unset, or the server can't be reached,
// the client switches to demo mode and answers from src/demo/backend.ts instead.
import { Platform } from "react-native";
import * as SecureStore from "expo-secure-store";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as demo from "@/demo/backend";
import type { Address, Order, Product, StoreMode, User } from "@/lib/types";
import type {
  ApiErrorBody,
  CheckoutItem,
  Job,
  Marketplace,
  OrderRequest,
  OrderResponse,
  OtpRequestResponse,
  OtpVerifyResponse,
  ProductResponse,
  QuoteResponse,
  SearchParams,
  SearchResponse,
  Suggestion,
  VerifyResponse,
} from "./types";

const RAW_URL = (process.env.EXPO_PUBLIC_API_URL ?? "").trim().replace(/\/+$/, "");
const BASE = RAW_URL ? RAW_URL + "/api/v1" : "";
const TOKEN_KEY = "sr.token";

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;
  constructor(status: number, body: ApiErrorBody | null) {
    super(body?.message ?? `Request failed (${status})`);
    this.status = status;
    this.errors = body?.errors;
  }
}

// ---------- mode ----------
type Mode = "unknown" | "live" | "demo";
let mode: Mode = BASE ? "unknown" : "demo";
let probing: Promise<Mode> | null = null;
const listeners = new Set<(m: Mode) => void>();

export function onModeChange(fn: (m: Mode) => void) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}
function setMode(m: Mode) {
  if (mode === m) return;
  mode = m;
  listeners.forEach((fn) => fn(m));
}
export function isDemo() {
  return mode === "demo";
}

async function resolveMode(): Promise<Mode> {
  if (mode !== "unknown") return mode;
  probing ??= fetchWithTimeout(BASE + "/marketplaces", { headers: { Accept: "application/json" } }, 4000)
    .then((r) => (r.ok ? "live" : "demo") as Mode)
    .catch(() => "demo" as Mode)
    .then((m) => {
      setMode(m);
      probing = null;
      return m;
    });
  return probing;
}

async function fetchWithTimeout(url: string, init: RequestInit, ms = 15000) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), ms);
  try {
    return await fetch(url, { ...init, signal: ctrl.signal });
  } finally {
    clearTimeout(t);
  }
}

// ---------- token (secure store on device, AsyncStorage on web) ----------
export const tokenStore = {
  async get() {
    try {
      return Platform.OS === "web" ? await AsyncStorage.getItem(TOKEN_KEY) : await SecureStore.getItemAsync(TOKEN_KEY);
    } catch {
      return null;
    }
  },
  async set(token: string | null) {
    try {
      if (Platform.OS === "web") {
        if (token) await AsyncStorage.setItem(TOKEN_KEY, token);
        else await AsyncStorage.removeItem(TOKEN_KEY);
      } else if (token) await SecureStore.setItemAsync(TOKEN_KEY, token);
      else await SecureStore.deleteItemAsync(TOKEN_KEY);
    } catch {
      /* ignore */
    }
  },
};

// ---------- http ----------
type Query = Record<string, string | number | boolean | undefined | null>;

function qs(q?: Query) {
  if (!q) return "";
  const parts = Object.entries(q)
    .filter(([, v]) => v !== undefined && v !== null && v !== "" && v !== false)
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v === true ? 1 : String(v))}`);
  return parts.length ? "?" + parts.join("&") : "";
}

async function http<T>(method: string, path: string, opts: { query?: Query; body?: unknown } = {}): Promise<T> {
  const token = await tokenStore.get();
  let res: Response;
  try {
    res = await fetchWithTimeout(BASE + path + qs(opts.query), {
      method,
      headers: {
        Accept: "application/json",
        ...(opts.body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
    });
  } catch (e) {
    throw new ApiError(0, { message: "Can't reach Skydrop right now. Check your connection." });
  }
  const text = await res.text();
  const json = text ? (JSON.parse(text) as unknown) : null;
  if (!res.ok) throw new ApiError(res.status, json as ApiErrorBody | null);
  return json as T;
}

/** Runs the live call, or the demo implementation when the API isn't available. */
async function either<T>(live: () => Promise<T>, local: () => T | Promise<T>): Promise<T> {
  const m = await resolveMode();
  if (m === "demo") return local();
  return live();
}

// ---------- endpoints ----------
export const api = {
  marketplaces: () =>
    either(
      () => http<{ items: Marketplace[] }>("GET", "/marketplaces").then((r) => r.items),
      () => demo.marketplaces(),
    ),

  search: (p: SearchParams) =>
    either(
      () => http<SearchResponse>("GET", "/search", { query: { ...p, perPage: undefined } }),
      () => demo.search(p),
    ),

  suggest: (q: string, store: StoreMode) =>
    either(
      () => http<{ items: Suggestion[] }>("GET", "/suggest", { query: { q, store } }).then((r) => r.items),
      () => demo.suggest(q, store),
    ),

  product: (id: string) =>
    either(
      () => http<ProductResponse>("GET", `/products/${encodeURIComponent(id)}`),
      () => {
        const product = demo.product(id);
        if (!product) throw new ApiError(404, { message: "Product not found" });
        return { status: "ready" as const, product };
      },
    ),

  products: (ids: string[]) =>
    ids.length
      ? either(
          () => http<{ items: Product[] }>("GET", "/products", { query: { ids: ids.slice(0, 100).join(",") } }).then((r) => r.items),
          () => demo.products(ids),
        )
      : Promise.resolve([] as Product[]),

  /** Home rails. The API has no rail endpoint, so live mode uses /search sorts. */
  rail: (store: StoreMode, kind: "popular" | "deals", category?: string) =>
    either(
      () =>
        http<SearchResponse>("GET", "/search", {
          query: { store, category, sort: kind === "deals" ? "discount" : "relevance" },
        }).then((r) => r.items.slice(0, 12)),
      () => demo.rail(store, kind, category),
    ),

  related: (p: Product) =>
    either(
      () =>
        http<SearchResponse>("GET", "/search", { query: { store: p.market, category: p.category, sort: "rating" } }).then((r) =>
          r.items.filter((x) => x.id !== p.id).slice(0, 8),
        ),
      () => demo.related(p),
    ),

  job: (id: string) => either(() => http<Job>("GET", `/jobs/${id}`), () => ({ id, status: "done" as const, type: "demo" })),

  // auth
  otpRequest: (phone: string) =>
    either(
      () => http<OtpRequestResponse>("POST", "/auth/otp/request", { body: { phone } }),
      () => demo.otpRequest(phone),
    ),
  otpVerify: async (phone: string, code: string, name?: string) => {
    const r = await either(
      () => http<OtpVerifyResponse>("POST", "/auth/otp/verify", { body: { phone, code, name } }),
      () => demo.otpVerify(phone, code, name),
    );
    await tokenStore.set(r.token);
    return r;
  },
  me: () =>
    either(
      () => http<{ user: User }>("GET", "/me").then((r) => r.user),
      () => demo.me(),
    ),
  updateMe: (patch: { name?: string; email?: string }) =>
    either(
      () => http<{ user: User }>("PATCH", "/me", { body: patch }).then((r) => r.user),
      () => demo.updateMe(patch),
    ),
  logout: async () => {
    await either(
      () => http<unknown>("POST", "/auth/logout").catch(() => null),
      () => demo.signOut(),
    );
    await tokenStore.set(null);
  },

  // addresses
  addresses: () =>
    either(
      () => http<{ items: Address[] }>("GET", "/addresses").then((r) => r.items),
      () => demo.addresses(),
    ),
  saveAddress: (a: Omit<Address, "id"> & { id?: string }) =>
    either(
      () =>
        (a.id
          ? http<{ address: Address }>("PATCH", `/addresses/${a.id}`, { body: a })
          : http<{ address: Address }>("POST", "/addresses", { body: a })
        ).then((r) => r.address),
      () => demo.saveAddress(a),
    ),
  deleteAddress: (id: string) =>
    either(
      () => http<unknown>("DELETE", `/addresses/${id}`).then(() => undefined),
      () => demo.deleteAddress(id),
    ),

  // wishlist (server copy; the app keeps a local copy too so it works signed out)
  wishlist: () =>
    either(
      () => http<{ items: Product[] }>("GET", "/wishlist").then((r) => r.items.map((p) => p.id)),
      () => demo.wishlistIds(),
    ),
  wishAdd: (productId: string, all: string[]) =>
    either(
      () => http<unknown>("POST", "/wishlist", { body: { productId } }).then(() => undefined),
      () => demo.setWishlistIds(all),
    ),
  wishRemove: (productId: string, all: string[]) =>
    either(
      () => http<unknown>("DELETE", `/wishlist/${productId}`).then(() => undefined),
      () => demo.setWishlistIds(all),
    ),

  // checkout
  verify: (items: CheckoutItem[]) =>
    either(
      () => http<VerifyResponse>("POST", "/checkout/verify", { body: { items: items.map(stripWeight) } }),
      () => demo.verify(items),
    ),
  quote: (items: CheckoutItem[], coupon?: string) =>
    either(
      () => http<QuoteResponse>("POST", "/checkout/quote", { body: { items: items.map(stripWeight), coupon } }),
      () => demo.quote(items, coupon),
    ),

  // orders
  placeOrder: (req: OrderRequest, address: Address) =>
    either(
      () => http<OrderResponse>("POST", "/orders", { body: { ...req, items: req.items.map(stripWeight) } }),
      () => demo.placeOrder(req, address),
    ),
  orders: () =>
    either(
      () => http<{ items: Order[] }>("GET", "/orders").then((r) => r.items),
      () => demo.orders(),
    ),
  order: (id: string) =>
    either(
      () => http<{ order: Order }>("GET", `/orders/${id}`).then((r) => r.order),
      async () => {
        const o = await demo.order(id);
        if (!o) throw new ApiError(404, { message: "Order not found" });
        return o;
      },
    ),
  cancelOrder: (id: string) =>
    either(
      () => http<{ order: Order }>("POST", `/orders/${id}/cancel`).then((r) => r.order),
      () => demo.cancelOrder(id),
    ),
};

function stripWeight({ weightKg: _w, ...rest }: CheckoutItem) {
  return rest;
}

export { resolveMode };
