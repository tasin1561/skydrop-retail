// Messaging endpoints: notification centre, Expo push token, cart sync (docs/API.md "Notifications").
// Demo mode answers from the locally stored demo orders.
import { http, isDemo, resolveMode } from "./client";
import * as demo from "@/demo/backend";
import { DEMO_STAGE_MS, STAGES, stageIndex } from "@/lib/orderStatus";
import type { CartItem } from "@/lib/types";
import AsyncStorage from "@react-native-async-storage/async-storage";

export type AppNotification = {
  id: number;
  type: string;
  title: string;
  body: string | null;
  icon: string | null;
  link: string | null;
  image: string | null;
  data: Record<string, unknown>;
  read: boolean;
  createdAt: string;
};
export type NotificationsPage = { items: AppNotification[]; unreadCount: number; page: number; total: number; hasMore: boolean };

const DEMO_KEY = "sr:notif-demo";
type Marks = { read: number[]; removed: number[] };

async function marks(): Promise<Marks> {
  try {
    return { read: [], removed: [], ...JSON.parse((await AsyncStorage.getItem(DEMO_KEY)) ?? "{}") };
  } catch {
    return { read: [], removed: [] };
  }
}
const saveMarks = (m: Marks) => AsyncStorage.setItem(DEMO_KEY, JSON.stringify(m)).catch(() => {});
const hash = (s: string) => {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return Math.abs(h);
};

async function demoPage(): Promise<NotificationsPage> {
  const m = await marks();
  const out: AppNotification[] = [];
  for (const o of await demo.orders()) {
    const created = new Date(o.createdAt).getTime();
    const upto = o.status === "cancelled" ? 0 : stageIndex(o.status);
    STAGES.slice(0, upto + 1).forEach((s, i) =>
      out.push({
        id: hash(o.id + s.status), type: s.status === "placed" ? "order_placed" : `order_${s.status}`,
        title: s.status === "placed" ? `Order ${o.id} placed` : s.label.replace("{store}", "the store"), body: s.detail,
        icon: s.icon, link: `/orders/${o.id}`, image: o.items[0]?.image ?? null, data: { orderId: o.id }, read: false,
        createdAt: new Date(created + i * DEMO_STAGE_MS).toISOString(),
      }),
    );
  }
  out.push({ id: 1, type: "welcome", title: "Welcome to Skydrop Retail", body: "Shop Amazon India, Flipkart and BigBasket in taka.",
    icon: "spark", link: "/", image: null, data: {}, read: false, createdAt: new Date(Date.now() - 3 * 86_400_000).toISOString() });
  const items = out.filter((n) => !m.removed.includes(n.id)).map((n) => ({ ...n, read: m.read.includes(n.id) }))
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  return { items, unreadCount: items.filter((n) => !n.read).length, page: 1, total: items.length, hasMore: false };
}

async function live() {
  return (await resolveMode()) === "live" && !isDemo();
}

export const messagingApi = {
  notifications: async (page = 1): Promise<NotificationsPage> =>
    (await live()) ? http<NotificationsPage>("GET", "/notifications", { query: { page, perPage: 20 } }) : demoPage(),
  read: async (id: number) => {
    if (await live()) return http<{ unreadCount: number }>("POST", `/notifications/${id}/read`);
    const m = await marks();
    await saveMarks({ ...m, read: [...m.read, id] });
  },
  readAll: async (ids: number[]) => {
    if (await live()) return http<{ unreadCount: number }>("POST", "/notifications/read-all");
    const m = await marks();
    await saveMarks({ ...m, read: Array.from(new Set([...m.read, ...ids])) });
  },
  remove: async (id: number) => {
    if (await live()) return http<{ unreadCount: number }>("DELETE", `/notifications/${id}`);
    const m = await marks();
    await saveMarks({ ...m, removed: [...m.removed, id] });
  },
  expoToken: async (token: string, platform: string) => {
    if (await live()) await http("POST", "/push/expo-token", { body: { token, platform } });
  },
  removeExpoToken: async (token: string) => {
    if (await live()) await http("DELETE", "/push/expo-token", { body: { token } }).catch(() => null);
  },
  syncCart: async (items: CartItem[]) => {
    if (!(await live())) return;
    await http("PUT", "/cart", {
      body: { items: items.map((i) => ({ productId: i.productId, market: i.market, title: i.title, image: i.image, variant: i.variant ?? null, unitBdt: i.unitBdt, qty: i.qty })) },
    });
  },
};
