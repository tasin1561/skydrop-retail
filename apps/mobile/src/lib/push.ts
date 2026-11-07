// Push notifications (Expo push service → POST /api/v1/push/expo-token).
//
// GUARD: expo-notifications is NOT in package.json yet, and Metro cannot bundle an import of a missing package,
// so this module is written against a small interface and stays inactive until the module is provided:
//
//   npx expo install expo-notifications expo-device
//   // app.json → "plugins": [..., "expo-notifications"]  and an EAS projectId (extra.eas.projectId)
//   // src/app/_layout.tsx (top):
//   import * as Notifications from "expo-notifications";
//   import { setNotificationsModule } from "@/lib/push";
//   setNotificationsModule(Notifications);
//
// After that, registration (after login), foreground display and tap → deep link all work. See README "Push notifications".
import { Platform } from "react-native";
import Constants from "expo-constants";
import { router } from "expo-router";
import { messagingApi } from "@/api/messaging";
import { isOn } from "@/lib/features";

type Perm = { status: string; granted?: boolean; canAskAgain?: boolean };
type Response = { notification: { request: { content: { data?: Record<string, unknown> } } } };
/** The parts of expo-notifications this app uses. */
export type NotificationsModule = {
  getPermissionsAsync(): Promise<Perm>;
  requestPermissionsAsync(): Promise<Perm>;
  getExpoPushTokenAsync(o?: { projectId?: string }): Promise<{ data: string }>;
  setNotificationChannelAsync?(id: string, c: { name: string; importance: number; vibrationPattern?: number[]; lightColor?: string }): Promise<unknown>;
  setNotificationHandler(h: { handleNotification: () => Promise<Record<string, boolean>> }): void;
  addNotificationResponseReceivedListener(f: (r: Response) => void): { remove(): void };
  getLastNotificationResponseAsync?(): Promise<Response | null>;
};

let N: NotificationsModule | null = null;
let token: string | null = null;

export function setNotificationsModule(mod: unknown) {
  N = mod as NotificationsModule;
  N.setNotificationHandler({
    handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: true, shouldShowAlert: true }),
  });
}

export function pushAvailable() {
  return N !== null && Platform.OS !== "web";
}

/** Asks for permission (once) and sends the Expo token to the API. Call after sign-in. */
export async function registerForPush(): Promise<string | null> {
  if (!N || Platform.OS === "web" || !isOn("push")) return null;
  try {
    if (Platform.OS === "android") {
      await N.setNotificationChannelAsync?.("default", { name: "Order updates", importance: 4, vibrationPattern: [0, 200, 120, 200], lightColor: "#0b6bcb" });
    }
    let p = await N.getPermissionsAsync();
    if (p.status !== "granted" && p.canAskAgain !== false) p = await N.requestPermissionsAsync();
    if (p.status !== "granted") return null;
    const extra = Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined;
    const projectId = extra?.eas?.projectId ?? (Constants as unknown as { easConfig?: { projectId?: string } }).easConfig?.projectId;
    token = (await N.getExpoPushTokenAsync(projectId ? { projectId } : undefined)).data;
    await messagingApi.expoToken(token, Platform.OS);
    return token;
  } catch {
    return null;
  }
}

/** Forget this device on sign-out. */
export async function unregisterPush() {
  if (token) await messagingApi.removeExpoToken(token);
  token = null;
}

/** Web path or app route from a notification → app route. /orders/X → /order/X, /p/X → /product/X. */
export function routeFor(data: { route?: unknown; link?: unknown } | null | undefined): string {
  if (typeof data?.route === "string" && data.route.startsWith("/")) return data.route;
  const link = typeof data?.link === "string" ? data.link : "";
  const path = link.replace(/^https?:\/\/[^/]+/, "").split("?")[0];
  let m = path.match(/^\/orders\/([^/#]+)/);
  if (m) return `/order/${m[1]}`;
  m = path.match(/^\/p\/([^/#]+)/);
  if (m) return `/product/${m[1]}`;
  if (path === "/cart") return "/cart";
  if (path.startsWith("/account/points")) return "/points";
  return "/notifications";
}

export function openFromNotification(data: { route?: unknown; link?: unknown } | null | undefined) {
  router.push(routeFor(data) as never);
}

/** Tap on a push (also the one that cold-started the app) → deep link. Returns an unsubscribe. */
export function listenForTaps(): () => void {
  if (!N) return () => {};
  N.getLastNotificationResponseAsync?.()
    .then((r) => r && openFromNotification(r.notification.request.content.data))
    .catch(() => {});
  const sub = N.addNotificationResponseReceivedListener((r) => openFromNotification(r.notification.request.content.data));
  return () => sub.remove();
}
