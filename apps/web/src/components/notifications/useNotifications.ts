"use client";

// Notification centre state shared by the header bell and /notifications.
// API mode: GET /notifications, polled every 60 s while the tab is visible, refetched on focus.
// Demo mode (no NEXT_PUBLIC_API_URL): items generated from the locally stored orders; read/deleted state in localStorage.
import { useCallback, useEffect, useSyncExternalStore } from "react";
import { api, apiEnabled, type AppNotification } from "@/lib/apiClient";
import { currentStatus, stageIndex, STAGES, DEMO_STAGE_MS } from "@/lib/orderStatus";
import { formatBdt } from "@/lib/pricing";
import type { Order } from "@/lib/types";
import { useStore } from "@/state/store";
import { useFeature } from "@/components/features/FeaturesProvider";

type State = { items: AppNotification[]; unread: number; loaded: boolean; loading: boolean; hasMore: boolean; page: number };

const EMPTY: State = { items: [], unread: 0, loaded: false, loading: false, hasMore: false, page: 1 };
const DEMO_KEY = "skydrop-retail:notifications-demo";
const POLL_MS = 60_000;

let state: State = EMPTY;
const subs = new Set<() => void>();
const set = (patch: Partial<State>) => {
  state = { ...state, ...patch };
  subs.forEach((f) => f());
};
const subscribe = (f: () => void) => {
  subs.add(f);
  return () => subs.delete(f);
};

// ---------- demo ----------
type DemoMarks = { read: number[]; removed: number[] };
function demoMarks(): DemoMarks {
  try {
    return { read: [], removed: [], ...JSON.parse(localStorage.getItem(DEMO_KEY) ?? "{}") };
  } catch {
    return { read: [], removed: [] };
  }
}
function saveDemo(m: DemoMarks) {
  try {
    localStorage.setItem(DEMO_KEY, JSON.stringify(m));
  } catch {
    /* ignore */
  }
}
function hashId(s: string) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return Math.abs(h);
}
const STATUS_ICON: Record<string, string> = {
  placed: "receipt", verified: "check", purchased: "package", india_hub: "package", in_transit: "plane",
  dhaka_hub: "package", out_for_delivery: "truck", delivered: "check", cancelled: "x",
};

export function demoNotifications(orders: Order[], now = Date.now()): AppNotification[] {
  const marks = demoMarks();
  const out: AppNotification[] = [];
  for (const o of orders) {
    const created = new Date(o.createdAt).getTime();
    const status = currentStatus(o, now);
    const reached = status === "cancelled" ? 0 : stageIndex(status);
    STAGES.slice(0, reached + 1).forEach((s, i) => {
      const at = s.status === "placed" ? created : created + i * DEMO_STAGE_MS;
      out.push({
        id: hashId(o.id + s.status), type: s.status === "placed" ? "order_placed" : `order_${s.status}`,
        title: s.status === "placed" ? `Order ${o.id} placed` : s.label.replace("{store}", "the store"),
        body: s.status === "placed" ? `Total ${formatBdt(o.total)}. We will verify price and stock, then buy it in India.` : s.detail,
        icon: STATUS_ICON[s.status] ?? "info", link: `/orders/${o.id}`, image: o.items[0]?.image ?? null, data: { orderId: o.id },
        read: false, createdAt: new Date(at).toISOString(),
      });
    });
    if (status === "cancelled") {
      out.push({ id: hashId(o.id + "cancelled"), type: "order_cancelled", title: `Order ${o.id} cancelled`, body: "Any payment is refunded to your original method.",
        icon: "x", link: `/orders/${o.id}`, image: null, data: { orderId: o.id }, read: false, createdAt: new Date(created + 60_000).toISOString() });
    }
  }
  out.push({ id: 1, type: "welcome", title: "Welcome to Skydrop Retail", body: "Shop Amazon India, Flipkart and BigBasket in taka. Skydrop delivers to your door.",
    icon: "spark", link: "/", image: null, data: {}, read: false, createdAt: new Date(now - 3 * 86_400_000).toISOString() });
  return out
    .filter((n) => !marks.removed.includes(n.id))
    .map((n) => ({ ...n, read: marks.read.includes(n.id) }))
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

// ---------- actions ----------
let demoOrders: Order[] = [];

async function fetchPage(page = 1) {
  if (!apiEnabled) {
    const items = demoNotifications(demoOrders);
    set({ items, unread: items.filter((n) => !n.read).length, loaded: true, loading: false, hasMore: false, page: 1 });
    return;
  }
  set({ loading: true });
  try {
    const r = await api.notifications(page, 20);
    set({
      items: page === 1 ? r.items : [...state.items, ...r.items.filter((n) => !state.items.some((x) => x.id === n.id))],
      unread: r.unreadCount, hasMore: r.hasMore, page: r.page, loaded: true, loading: false,
    });
  } catch {
    set({ loading: false, loaded: true });
  }
}

function markRead(id: number) {
  const n = state.items.find((x) => x.id === id);
  if (!n || n.read) return;
  set({ items: state.items.map((x) => (x.id === id ? { ...x, read: true } : x)), unread: Math.max(0, state.unread - 1) });
  if (!apiEnabled) {
    const m = demoMarks();
    saveDemo({ ...m, read: [...m.read, id] });
    return;
  }
  api.notificationRead(id).then((r) => set({ unread: r.unreadCount })).catch(() => {});
}

function markAllRead() {
  set({ items: state.items.map((x) => ({ ...x, read: true })), unread: 0 });
  if (!apiEnabled) {
    const m = demoMarks();
    saveDemo({ ...m, read: Array.from(new Set([...m.read, ...state.items.map((x) => x.id)])) });
    return;
  }
  api.notificationsReadAll().catch(() => {});
}

function remove(id: number) {
  const n = state.items.find((x) => x.id === id);
  set({ items: state.items.filter((x) => x.id !== id), unread: n && !n.read ? Math.max(0, state.unread - 1) : state.unread });
  if (!apiEnabled) {
    const m = demoMarks();
    saveDemo({ ...m, removed: [...m.removed, id] });
    return;
  }
  api.notificationDelete(id).then((r) => set({ unread: r.unreadCount })).catch(() => {});
}

// ---------- polling (one timer for all mounted consumers) ----------
let consumers = 0;
let timer: ReturnType<typeof setInterval> | null = null;
const onVisible = () => {
  if (document.visibilityState === "visible") fetchPage(1);
};
function startPolling() {
  if (consumers++ > 0) return;
  timer = setInterval(() => {
    if (document.visibilityState === "visible") fetchPage(1); // paused while the tab is hidden
  }, POLL_MS);
  document.addEventListener("visibilitychange", onVisible);
  window.addEventListener("focus", onVisible);
}
function stopPolling() {
  if (--consumers > 0) return;
  if (timer) clearInterval(timer);
  timer = null;
  document.removeEventListener("visibilitychange", onVisible);
  window.removeEventListener("focus", onVisible);
}

/** Notification centre for the signed-in shopper. `enabled` is false when signed out or the feature is off. */
export function useNotifications() {
  const { user, token, orders, ready } = useStore();
  const featureOn = useFeature("in_app_notifications");
  const enabled = featureOn && ready && !!user && (!apiEnabled || !!token);
  const s = useSyncExternalStore(subscribe, () => state, () => EMPTY);

  useEffect(() => {
    if (!enabled) {
      state = EMPTY;
      return;
    }
    demoOrders = orders;
    fetchPage(1);
    startPolling();
    return stopPolling;
  }, [enabled, token, orders]);

  const loadMore = useCallback(() => {
    if (state.hasMore && !state.loading) fetchPage(state.page + 1);
  }, []);

  return { ...(enabled ? s : EMPTY), enabled, refresh: () => fetchPage(1), loadMore, markRead, markAllRead, remove };
}

/** "Today" / "Earlier" groups, newest first. */
export function groupByDay(items: AppNotification[], now = new Date()) {
  const start = new Date(now);
  start.setHours(0, 0, 0, 0);
  const today = items.filter((n) => new Date(n.createdAt) >= start);
  const earlier = items.filter((n) => new Date(n.createdAt) < start);
  return [
    { label: "Today", items: today },
    { label: "Earlier", items: earlier },
  ].filter((g) => g.items.length > 0);
}

export function timeAgo(iso: string, now = Date.now()) {
  const s = Math.max(0, Math.round((now - new Date(iso).getTime()) / 1000));
  if (s < 60) return "just now";
  if (s < 3600) return `${Math.floor(s / 60)} min ago`;
  if (s < 86400) return `${Math.floor(s / 3600)} h ago`;
  const d = Math.floor(s / 86400);
  return d === 1 ? "yesterday" : d < 7 ? `${d} days ago` : new Date(iso).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
}
