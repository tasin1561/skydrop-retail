"use client";

// Client-side store for the demo. Cart, wishlist, account, addresses, orders and the
// marketplace toggles live in localStorage until the Laravel API is connected.
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { Address, CartItem, MarketId, Order, StoreMode, User } from "@/lib/types";
import { DEFAULT_ENABLED } from "@/lib/markets";

type State = {
  cart: CartItem[];
  wishlist: string[];
  user: User | null;
  addresses: Address[];
  orders: Order[];
  enabled: Record<MarketId, boolean>;
  mode: StoreMode;
  theme: "light" | "dark" | "system";
};

const INITIAL: State = {
  cart: [],
  wishlist: [],
  user: null,
  addresses: [],
  orders: [],
  enabled: DEFAULT_ENABLED,
  mode: "all",
  theme: "system",
};

const KEY = "skydrop-retail:v1";

type Ctx = State & {
  ready: boolean;
  addToCart: (item: CartItem) => void;
  setQty: (productId: string, variant: string | undefined, qty: number) => void;
  removeFromCart: (productId: string, variant?: string) => void;
  clearCart: () => void;
  toggleWish: (id: string) => void;
  login: (u: User) => void;
  logout: () => void;
  saveAddress: (a: Address) => void;
  removeAddress: (id: string) => void;
  placeOrder: (o: Order) => void;
  cancelOrder: (id: string) => void;
  setEnabled: (m: MarketId, on: boolean) => void;
  setMode: (m: StoreMode) => void;
  setTheme: (t: State["theme"]) => void;
  cartCount: number;
};

const StoreCtx = createContext<Ctx | null>(null);

export function StoreProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<State>(INITIAL);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(KEY);
      // hydrate once from storage after mount (server render has no localStorage)
      // eslint-disable-next-line react-hooks/set-state-in-effect
      if (raw) setState({ ...INITIAL, ...JSON.parse(raw) });
    } catch {
      /* storage unavailable: run in memory */
    }
    setReady(true);
  }, []);

  useEffect(() => {
    if (!ready) return;
    try {
      localStorage.setItem(KEY, JSON.stringify(state));
    } catch {
      /* ignore */
    }
  }, [state, ready]);

  useEffect(() => {
    const root = document.documentElement;
    const dark =
      state.theme === "dark" ||
      (state.theme === "system" && window.matchMedia("(prefers-color-scheme: dark)").matches);
    root.dataset.theme = dark ? "dark" : "light";
  }, [state.theme]);

  const update = useCallback((fn: (s: State) => State) => setState((s) => fn(s)), []);

  const api = useMemo<Ctx>(
    () => ({
      ...state,
      ready,
      cartCount: state.cart.reduce((n, i) => n + i.qty, 0),
      addToCart: (item) =>
        update((s) => {
          const found = s.cart.find((c) => c.productId === item.productId && c.variant === item.variant);
          return {
            ...s,
            cart: found
              ? s.cart.map((c) => (c === found ? { ...c, qty: Math.min(10, c.qty + item.qty) } : c))
              : [...s.cart, item],
          };
        }),
      setQty: (id, variant, qty) =>
        update((s) => ({
          ...s,
          cart: s.cart.map((c) =>
            c.productId === id && c.variant === variant ? { ...c, qty: Math.max(1, Math.min(10, qty)) } : c,
          ),
        })),
      removeFromCart: (id, variant) =>
        update((s) => ({ ...s, cart: s.cart.filter((c) => !(c.productId === id && c.variant === variant)) })),
      clearCart: () => update((s) => ({ ...s, cart: [] })),
      toggleWish: (id) =>
        update((s) => ({
          ...s,
          wishlist: s.wishlist.includes(id) ? s.wishlist.filter((w) => w !== id) : [...s.wishlist, id],
        })),
      login: (u) => update((s) => ({ ...s, user: u })),
      logout: () => update((s) => ({ ...s, user: null })),
      saveAddress: (a) =>
        update((s) => {
          const others = s.addresses.filter((x) => x.id !== a.id).map((x) => (a.isDefault ? { ...x, isDefault: false } : x));
          return { ...s, addresses: [...others, { ...a, isDefault: a.isDefault || others.length === 0 }] };
        }),
      removeAddress: (id) => update((s) => ({ ...s, addresses: s.addresses.filter((a) => a.id !== id) })),
      placeOrder: (o) => update((s) => ({ ...s, orders: [o, ...s.orders], cart: [] })),
      cancelOrder: (id) =>
        update((s) => ({
          ...s,
          orders: s.orders.map((o) => (o.id === id ? { ...o, status: "cancelled" } : o)),
        })),
      setEnabled: (m, on) => update((s) => ({ ...s, enabled: { ...s.enabled, [m]: on } })),
      setMode: (m) => update((s) => ({ ...s, mode: m })),
      setTheme: (t) => update((s) => ({ ...s, theme: t })),
    }),
    [state, ready, update],
  );

  return <StoreCtx.Provider value={api}>{children}</StoreCtx.Provider>;
}

export function useStore() {
  const ctx = useContext(StoreCtx);
  if (!ctx) throw new Error("useStore must be used inside StoreProvider");
  return ctx;
}
