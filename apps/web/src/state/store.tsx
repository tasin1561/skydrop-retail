"use client";

// Client-side store. Cart, theme and store mode always live in localStorage. When
// NEXT_PUBLIC_API_URL is set, account, addresses, wishlist and orders sync with the Laravel API;
// without it the storefront runs fully in demo mode.
import { createContext, useCallback, useContext, useEffect, useMemo, useState, useSyncExternalStore } from "react";
import type { Address, CartItem, MarketId, Order, StoreMode, User } from "@/lib/types";
import { DEFAULT_ENABLED, MARKET_IDS } from "@/lib/markets";
import { api, apiEnabled, API_URL, setApiToken } from "@/lib/apiClient";

type State = {
  cart: CartItem[];
  wishlist: string[];
  user: User | null;
  addresses: Address[];
  orders: Order[];
  enabled: Record<MarketId, boolean>;
  mode: StoreMode;
  theme: "light" | "dark" | "system";
  token: string | null;
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
  token: null,
};

const KEY = "skydrop-retail:v1";

type Ctx = State & {
  ready: boolean;
  addToCart: (item: CartItem) => void;
  setQty: (productId: string, variant: string | undefined, qty: number) => void;
  removeFromCart: (productId: string, variant?: string) => void;
  clearCart: () => void;
  repriceCart: (prices: { productId: string; freshBdt: number }[]) => void;
  toggleWish: (id: string) => void;
  login: (u: User, token?: string) => void;
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

  // API mode: load the shopper's account data and the live marketplace switches once ready.
  useEffect(() => {
    if (!ready || !apiEnabled) return;
    let cancelled = false;
    fetch(`${API_URL}/marketplaces`, { headers: { Accept: "application/json" } })
      .then((r) => (r.ok ? r.json() : null))
      .then((d: { items?: { id: MarketId; enabled: boolean }[] } | null) => {
        if (cancelled || !d?.items) return;
        const enabled = { ...DEFAULT_ENABLED };
        for (const m of d.items) if (MARKET_IDS.includes(m.id)) enabled[m.id] = m.enabled;
        setState((s) => ({ ...s, enabled }));
      })
      .catch(() => {});
    if (!state.token) return;
    setApiToken(state.token);
    Promise.allSettled([api.me(), api.addresses(), api.orders(), api.wishlist()]).then(([me, addr, ord, wish]) => {
      if (cancelled) return;
      if (me.status === "rejected") {
        // token expired or revoked: sign out locally
        setApiToken(null);
        setState((s) => ({ ...s, token: null, user: null }));
        return;
      }
      setState((s) => ({
        ...s,
        user: me.value.user,
        addresses: addr.status === "fulfilled" ? addr.value.items.map((a) => ({ ...a, id: String(a.id) })) : s.addresses,
        orders: ord.status === "fulfilled" ? ord.value.items : s.orders,
        wishlist: wish.status === "fulfilled" ? wish.value.items.map((w) => w.id) : s.wishlist,
      }));
    });
    return () => {
      cancelled = true;
    };
     
  }, [ready, state.token]);

  const remote = (p: Promise<unknown>) => {
    p.catch(() => {
      /* keep the optimistic local change; the next sync corrects it */
    });
  };
  const synced = apiEnabled && !!state.token;

  const value = useMemo<Ctx>(
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
      repriceCart: (prices) =>
        update((s) => ({
          ...s,
          cart: s.cart.map((c) => {
            const p = prices.find((x) => x.productId === c.productId);
            return p ? { ...c, unitBdt: p.freshBdt } : c;
          }),
        })),
      toggleWish: (id) => {
        if (synced) remote(state.wishlist.includes(id) ? api.wishlistRemove(id) : api.wishlistAdd(id));
        update((s) => ({
          ...s,
          wishlist: s.wishlist.includes(id) ? s.wishlist.filter((w) => w !== id) : [...s.wishlist, id],
        }));
      },
      login: (u, token) => {
        if (token) setApiToken(token);
        update((s) => ({ ...s, user: u, token: token ?? s.token }));
      },
      logout: () => {
        if (synced) remote(api.logout());
        setApiToken(null);
        update((s) => ({ ...s, user: null, token: null, ...(apiEnabled ? { orders: [], addresses: [], wishlist: [] } : {}) }));
      },
      saveAddress: (a) => {
        if (synced)
          api
            .saveAddress(a)
            .then(({ address }) =>
              update((s) => ({ ...s, addresses: s.addresses.map((x) => (x.id === a.id ? { ...address, id: String(address.id) } : x)) })),
            )
            .catch(() => {});
        update((s) => {
          const others = s.addresses.filter((x) => x.id !== a.id).map((x) => (a.isDefault ? { ...x, isDefault: false } : x));
          return { ...s, addresses: [...others, { ...a, isDefault: a.isDefault || others.length === 0 }] };
        });
      },
      removeAddress: (id) => {
        if (synced && /^\d+$/.test(id)) remote(api.removeAddress(id));
        update((s) => ({ ...s, addresses: s.addresses.filter((a) => a.id !== id) }));
      },
      placeOrder: (o) => update((s) => ({ ...s, orders: [o, ...s.orders], cart: [] })),
      cancelOrder: (id) => {
        if (synced) remote(api.cancelOrder(id));
        update((s) => ({
          ...s,
          orders: s.orders.map((o) => (o.id === id ? { ...o, status: "cancelled" } : o)),
        }));
      },
      setEnabled: (m, on) => update((s) => ({ ...s, enabled: { ...s.enabled, [m]: on } })),
      setMode: (m) => update((s) => ({ ...s, mode: m })),
      setTheme: (t) => update((s) => ({ ...s, theme: t })),
    }),
     
    [state, ready, update, synced],
  );

  return <StoreCtx.Provider value={value}>{children}</StoreCtx.Provider>;
}

const noopSubscribe = () => () => {};

// True only after this component has hydrated. Components hydrate at different times
// (Suspense boundaries), so each consumer checks for itself.
export function useHydrated() {
  return useSyncExternalStore(
    noopSubscribe,
    () => true,
    () => false,
  );
}

export function useStore(): Ctx {
  const ctx = useContext(StoreCtx);
  const hydrated = useHydrated();
  if (!ctx) throw new Error("useStore must be used inside StoreProvider");
  // During hydration every consumer renders the same initial state the server rendered,
  // then re-renders with the shopper's saved state. This avoids hydration mismatches.
  if (!hydrated) return { ...ctx, ...INITIAL, ready: false, cartCount: 0 };
  return ctx;
}
