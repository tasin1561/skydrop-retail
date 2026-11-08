// Notification centre state shared by the Home bell and the Notifications screen.
// Polls every 60 s while the app is in the foreground and refetches when it comes back.
import { useCallback, useEffect, useSyncExternalStore } from "react";
import { AppState } from "react-native";
import { messagingApi, type AppNotification } from "@/api/messaging";
import { useFeature } from "@/lib/features";
import { useStore } from "@/state/store";
import { fmtRelative, type Lang } from "@/i18n/core";

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

/** "5 minutes ago", "yesterday", "৫ মিনিট আগে" (Intl.RelativeTimeFormat in the shopper's language). */
export function timeAgo(iso: string, lang: Lang = "en", now = Date.now()) {
  return fmtRelative(Math.min(new Date(iso).getTime(), now), lang, now);
}

/** Feather-friendly icon + color per notification type. */
export const CHIP: Record<string, [string, string]> = {
  order_placed: ["receipt", "#4b4b52"], payment_received: ["wallet", "#1a7f4b"], payment_failed: ["card", "#b42318"],
  payment_pending: ["clock", "#4b4b52"], order_verified: ["shield", "#4b4b52"], order_purchased: ["bag", "#4b4b52"],
  order_india_hub: ["package", "#4b4b52"], order_in_transit: ["plane", "#4b4b52"], order_dhaka_hub: ["pin", "#4b4b52"],
  order_out_for_delivery: ["truck", "#4b4b52"], order_delivered: ["check", "#1a7f4b"], order_cancelled: ["x", "#b42318"],
  review_request: ["star", "#4b4b52"], points_expiring: ["tag", "#4b4b52"], price_drop: ["tag", "#1a7f4b"],
  back_in_stock: ["bolt", "#4b4b52"], cart_reminder_1h: ["cart", "#4b4b52"], welcome: ["spark", "#4b4b52"],
  broadcast: ["volume-2", "#4b4b52"], custom: ["info", "#4b4b52"],
};
