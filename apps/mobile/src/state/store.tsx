// App-wide state: cart, coupon, wishlist, session. Persisted with AsyncStorage (token in SecureStore).
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { api, isDemo, onModeChange, onSessionExpired, resolveMode, tokenStore } from "@/api/client";
import type { CartItem, StoreMode, User } from "@/lib/types";
import { cartKey, computeTotals, couponFromApi, findCoupon, type Coupon, type Totals } from "@/lib/cart";
import { messagingApi } from "@/api/messaging";
import { loadFeatures } from "@/lib/features";
import { registerForPush, unregisterPush } from "@/lib/push";

const K = { cart: "sr:cart", coupon: "sr:coupon", wish: "sr:wish", store: "sr:store", recent: "sr:recent", watches: "sr:watches" };

type Store = {
  ready: boolean;
  demo: boolean;
  // store mode (home switcher)
  mode: StoreMode;
  setMode: (m: StoreMode) => void;
  enabled: StoreMode[];
  // cart
  cart: CartItem[];
  addToCart: (item: CartItem) => void;
  setQty: (key: string, qty: number) => void;
  removeItem: (key: string) => void;
  clearCart: () => void;
  coupon: Coupon | null;
  setCoupon: (value: Coupon | string | null) => void;
  totals: Totals;
  // wishlist
  wish: string[];
  toggleWish: (id: string) => boolean;
  // recently viewed (last 20, local only) and price/stock alerts
  recent: string[];
  markViewed: (id: string) => void;
  watches: string[];
  setWatching: (id: string, on: boolean) => void;
  // session
  user: User | null;
  signIn: (u: User) => void;
  signOut: () => Promise<void>;
  setUser: (u: User) => void;
};

const Ctx = createContext<Store | null>(null);

export function StoreProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [demo, setDemo] = useState(true);
  const [mode, setModeState] = useState<StoreMode>("all");
  const [enabled, setEnabled] = useState<StoreMode[]>(["all", "amazon", "flipkart", "myntra"]);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [couponState, setCouponState] = useState<Coupon | null>(null);
  const [wish, setWish] = useState<string[]>([]);
  const [user, setUserState] = useState<User | null>(null);
  const [recent, setRecent] = useState<string[]>([]);
  const [watches, setWatches] = useState<string[]>([]);
  const loaded = useRef(false);

  useEffect(() => {
    (async () => {
      try {
        const [c, cp, w, s, r, wt] = await AsyncStorage.multiGet([K.cart, K.coupon, K.wish, K.store, K.recent, K.watches]);
        if (r[1]) setRecent(JSON.parse(r[1]));
        if (wt[1]) setWatches(JSON.parse(wt[1]));
        if (c[1]) setCart(JSON.parse(c[1]));
        if (cp[1]) {
          // Stored as JSON (server coupons); older builds stored the bare code.
          let saved: Coupon | null = null;
          try {
            saved = couponFromApi(JSON.parse(cp[1]));
          } catch {
            saved = findCoupon(cp[1]);
          }
          setCouponState(saved);
        }
        if (w[1]) setWish(JSON.parse(w[1]));
        if (s[1] === "amazon" || s[1] === "flipkart" || s[1] === "myntra" || s[1] === "all") setModeState(s[1]);
      } catch {
        /* first run */
      }
      loaded.current = true;
      setReady(true);
      await resolveMode();
      setDemo(isDemo());
      loadFeatures().catch(() => {}); // admin feature switches, fetched once
      try {
        const mk = await api.marketplaces();
        const on = mk.filter((m) => m.enabled).map((m) => m.id);
        setEnabled(["all", ...on]);
        setModeState((cur) => (cur === "all" || on.includes(cur) ? cur : "all"));
      } catch {
        /* keep defaults */
      }
      if (await tokenStore.get()) {
        try {
          const u = await api.me();
          if (u) {
            setUserState(u);
            registerForPush().catch(() => {}); // messaging: refresh the Expo token for this device
          }
        } catch {
          /* token expired */
        }
      }
    })();
    const off = onModeChange((m) => setDemo(m === "demo"));
    const offSession = onSessionExpired(() => setUserState(null));
    return () => {
      off();
      offSession();
    };
  }, []);

  // persistence
  useEffect(() => {
    if (loaded.current) AsyncStorage.setItem(K.cart, JSON.stringify(cart)).catch(() => {});
  }, [cart]);

  // Messaging: debounced cart sync for signed-in shoppers (abandoned-cart reminders).
  const lastSynced = useRef<string | null>(null);
  useEffect(() => {
    if (!user || demo || !loaded.current) return;
    const json = JSON.stringify(cart.map((i) => [i.productId, i.variant ?? null, i.qty, i.unitBdt]));
    if (json === lastSynced.current) return;
    const t = setTimeout(() => {
      messagingApi
        .syncCart(cart)
        .then(() => {
          lastSynced.current = json;
        })
        .catch(() => {});
    }, 1500);
    return () => clearTimeout(t);
  }, [cart, user, demo]);
  useEffect(() => {
    if (loaded.current) AsyncStorage.setItem(K.wish, JSON.stringify(wish)).catch(() => {});
  }, [wish]);

  useEffect(() => {
    if (loaded.current) AsyncStorage.setItem(K.recent, JSON.stringify(recent)).catch(() => {});
  }, [recent]);
  useEffect(() => {
    if (loaded.current) AsyncStorage.setItem(K.watches, JSON.stringify(watches)).catch(() => {});
  }, [watches]);

  const markViewed = useCallback((id: string) => {
    setRecent((cur) => (cur[0] === id ? cur : [id, ...cur.filter((x) => x !== id)].slice(0, 20)));
  }, []);
  const setWatching = useCallback((id: string, on: boolean) => {
    setWatches((cur) => (on ? Array.from(new Set([...cur, id])) : cur.filter((x) => x !== id)));
  }, []);

  const setMode = useCallback((m: StoreMode) => {
    setModeState(m);
    AsyncStorage.setItem(K.store, m).catch(() => {});
  }, []);

  const addToCart = useCallback((item: CartItem) => {
    setCart((list) => {
      const key = cartKey(item);
      const at = list.findIndex((i) => cartKey(i) === key);
      if (at < 0) return [...list, item];
      const next = [...list];
      next[at] = { ...next[at], qty: Math.min(10, next[at].qty + item.qty), unitBdt: item.unitBdt };
      return next;
    });
  }, []);

  const setQty = useCallback((key: string, qty: number) => {
    setCart((list) => list.map((i) => (cartKey(i) === key ? { ...i, qty: Math.max(1, Math.min(10, qty)) } : i)));
  }, []);

  const removeItem = useCallback((key: string) => setCart((list) => list.filter((i) => cartKey(i) !== key)), []);
  const clearCart = useCallback(() => setCart([]), []);

  /** Pass a validated Coupon (from the server quote or a demo code), or "" / null to remove it. */
  const setCoupon = useCallback((value: Coupon | string | null) => {
    const next = typeof value === "string" ? findCoupon(value) : value;
    setCouponState(next);
    if (next) AsyncStorage.setItem(K.coupon, JSON.stringify(next)).catch(() => {});
    else AsyncStorage.removeItem(K.coupon).catch(() => {});
  }, []);

  const toggleWish = useCallback(
    (id: string) => {
      const has = wish.includes(id);
      const next = has ? wish.filter((x) => x !== id) : [id, ...wish];
      setWish(next);
      if (user) (has ? api.wishRemove(id, next) : api.wishAdd(id, next)).catch(() => {});
      return !has;
    },
    [wish, user],
  );

  const signIn = useCallback((u: User) => {
    setUserState(u);
    registerForPush().catch(() => {}); // messaging: send the Expo push token after login
    // merge the server wishlist into the local one
    api
      .wishlist()
      .then((ids) => setWish((cur) => Array.from(new Set([...cur, ...ids]))))
      .catch(() => {});
  }, []);

  const signOut = useCallback(async () => {
    await unregisterPush().catch(() => {});
    await api.logout().catch(() => {});
    setUserState(null);
  }, []);

  const coupon = couponState;
  const totals = useMemo(() => computeTotals(cart, coupon), [cart, coupon]);

  const value: Store = {
    ready,
    demo,
    mode,
    setMode,
    enabled,
    cart,
    addToCart,
    setQty,
    removeItem,
    clearCart,
    coupon,
    setCoupon,
    totals,
    wish,
    toggleWish,
    recent,
    markViewed,
    watches,
    setWatching,
    user,
    signIn,
    signOut,
    setUser: setUserState,
  };
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useStore() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useStore outside StoreProvider");
  return v;
}
