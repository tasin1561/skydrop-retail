// Notification centre state shared by the Home bell and the Notifications screen.
// Polls every 60 s while the app is in the foreground and refetches when it comes back.
import { useCallback, useEffect, useSyncExternalStore } from "react";
import { AppState } from "react-native";
import { messagingApi, type AppNotification } from "@/api/messaging";
import { useFeature } from "@/lib/features";
import { useStore } from "@/state/store";

type State = { items: AppNotification[]; unread: number; loaded: boolean; loading: boolean; hasMore: boolean; page: number };
const EMPTY: State = { items: [], unread: 0, loaded: false, loading: false, hasMore: false, page: 1 };
let state: State = EMPTY;
const subs = new Set<() => void>();
const set = (p: Partial<State>) => {
  state = { ...state, ...p };
  subs.forEach((f) => f());
};
const subscribe = (f: () => void) => {
  subs.add(f);
  return () => {
    subs.delete(f);
  };
};

async function fetchPage(page = 1) {
  set({ loading: true });
  try {
    const r = await messagingApi.notifications(page);
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
  messagingApi.read(id).catch(() => {});
}
function markAllRead() {
  const ids = state.items.map((x) => x.id);
  set({ items: state.items.map((x) => ({ ...x, read: true })), unread: 0 });
  messagingApi.readAll(ids).catch(() => {});
}
function remove(id: number) {
  const n = state.items.find((x) => x.id === id);
  set({ items: state.items.filter((x) => x.id !== id), unread: n && !n.read ? Math.max(0, state.unread - 1) : state.unread });
  messagingApi.remove(id).catch(() => {});
}

let consumers = 0;
let timer: ReturnType<typeof setInterval> | null = null;
let appSub: { remove(): void } | null = null;
function start() {
  if (consumers++ > 0) return;
  timer = setInterval(() => AppState.currentState === "active" && fetchPage(1), 60_000);
  appSub = AppState.addEventListener("change", (s) => s === "active" && fetchPage(1));
}
function stop() {
  if (--consumers > 0) return;
  if (timer) clearInterval(timer);
  appSub?.remove();
  timer = null;
  appSub = null;
}

export function useNotifications() {
  const { user, ready } = useStore();
  const on = useFeature("in_app_notifications");
  const enabled = on && ready && !!user;
  const s = useSyncExternalStore(subscribe, () => state, () => state);

  useEffect(() => {
    if (!enabled) return;
    fetchPage(1);
    start();
    return stop;
  }, [enabled, user]);

  const refresh = useCallback(() => fetchPage(1), []);
  const loadMore = useCallback(() => {
    if (state.hasMore && !state.loading) fetchPage(state.page + 1);
  }, []);

  return { ...(enabled ? s : EMPTY), enabled, refresh, loadMore, markRead, markAllRead, remove };
}

export function timeAgo(iso: string, now = Date.now()) {
  const s = Math.max(0, Math.round((now - new Date(iso).getTime()) / 1000));
  if (s < 60) return "just now";
  if (s < 3600) return `${Math.floor(s / 60)} min ago`;
  if (s < 86400) return `${Math.floor(s / 3600)} h ago`;
  const d = Math.floor(s / 86400);
  return d === 1 ? "yesterday" : d < 7 ? `${d} days ago` : new Date(iso).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
}

/** Feather-friendly icon + color per notification type. */
export const CHIP: Record<string, [string, string]> = {
  order_placed: ["receipt", "#0b6bcb"], payment_received: ["wallet", "#16a34a"], payment_failed: ["card", "#dc2626"],
  payment_pending: ["clock", "#d97706"], order_verified: ["shield", "#0b6bcb"], order_purchased: ["bag", "#0b6bcb"],
  order_india_hub: ["package", "#0891b2"], order_in_transit: ["plane", "#0891b2"], order_dhaka_hub: ["pin", "#0891b2"],
  order_out_for_delivery: ["truck", "#d97706"], order_delivered: ["check", "#16a34a"], order_cancelled: ["x", "#dc2626"],
  review_request: ["star", "#eab308"], points_expiring: ["tag", "#9333ea"], price_drop: ["tag", "#16a34a"],
  back_in_stock: ["bolt", "#0b6bcb"], cart_reminder_1h: ["cart", "#9333ea"], welcome: ["spark", "#0b6bcb"],
  broadcast: ["volume-2", "#9333ea"], custom: ["info", "#475569"],
};
