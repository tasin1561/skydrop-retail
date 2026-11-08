// Push notifications: Expo push service → POST /api/v1/push/expo-token (docs/API.md "Push devices").
//
// - The permission prompt is never shown on launch. It is offered after the shopper's first order (success screen)
//   and from Account → Notification settings (`enablePush()`).
// - On launch / sign-in, `registerForPush()` only refreshes the token when permission was already granted.
// - Taps (also the one that cold-started the app) deep-link to the order / product (`listenForTaps()`).
// - Credentials are not in the repo: Android needs google-services.json + the FCM V1 key in Expo, iOS an APNs key
//   created by `eas credentials`. See README "Push notifications".
import { Linking, Platform } from "react-native";
import Constants from "expo-constants";
import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { router } from "expo-router";
import { messagingApi } from "@/api/messaging";
import { isOn } from "@/lib/features";

export type PushState = "unsupported" | "granted" | "denied" | "undetermined";

const ASKED_KEY = "sr:push-asked";
let token: string | null = null;

if (Platform.OS !== "web") {
  // Show pushes while the app is open too (banner + inbox list).
  Notifications.setNotificationHandler({
    handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
  });
}

/** True on a real phone with the native module (not web, not a simulator). */
export function pushAvailable() {
  return Platform.OS !== "web" && Device.isDevice;
}

export async function pushState(): Promise<PushState> {
  if (!pushAvailable()) return "unsupported";
  try {
    const p = await Notifications.getPermissionsAsync();
    if (p.granted || p.status === "granted") return "granted";
    return p.canAskAgain === false ? "denied" : p.status === "denied" ? "denied" : "undetermined";
  } catch {
    return "unsupported";
  }
}

function projectId(): string | undefined {
  const extra = Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined;
  return extra?.eas?.projectId ?? (Constants as unknown as { easConfig?: { projectId?: string } }).easConfig?.projectId;
}

async function sendToken(): Promise<string | null> {
  if (Platform.OS === "android") {
    await Notifications.setNotificationChannelAsync("default", {
      name: "Order updates",
      importance: 6, // AndroidImportance.HIGH (heads-up banner)
      vibrationPattern: [0, 200, 120, 200],
      lightColor: "#233056",
    });
  }
  const id = projectId();
  token = (await Notifications.getExpoPushTokenAsync(id ? { projectId: id } : undefined)).data;
  await messagingApi.expoToken(token, Platform.OS);
  return token;
}

/** Launch / sign-in: refresh the token only when the shopper already allowed notifications (never prompts). */
export async function registerForPush(): Promise<string | null> {
  if (!pushAvailable() || !isOn("push")) return null;
  try {
    if ((await pushState()) !== "granted") return null;
    return await sendToken();
  } catch {
    return null;
  }
}

/** Asks for permission (from a button the shopper pressed) and registers the token. */
export async function enablePush(): Promise<PushState> {
  if (!pushAvailable() || !isOn("push")) return "unsupported";
  try {
    await AsyncStorage.setItem(ASKED_KEY, "1").catch(() => {});
    let p = await Notifications.getPermissionsAsync();
    if (!p.granted && p.canAskAgain !== false) p = await Notifications.requestPermissionsAsync();
    if (!p.granted) return p.canAskAgain === false ? "denied" : "undetermined";
    await sendToken().catch(() => null);
    return "granted";
  } catch {
    return "unsupported";
  }
}

/** Should the success screen offer push? Once per install, only when it could still be granted. */
export async function shouldOfferPush(): Promise<boolean> {
  if (!pushAvailable() || !isOn("push")) return false;
  if ((await AsyncStorage.getItem(ASKED_KEY).catch(() => null)) === "1") return false;
  return (await pushState()) === "undetermined";
}

export async function markPushOffered() {
  await AsyncStorage.setItem(ASKED_KEY, "1").catch(() => {});
}

export function openPhoneSettings() {
  Linking.openSettings().catch(() => {});
}

/** Forget this device on sign-out. */
export async function unregisterPush() {
  if (token) await messagingApi.removeExpoToken(token);
  token = null;
}

/** Web path or app route from a notification → app route. /orders/X → /order/X, /p/X → /product/X. */
export function routeFor(data: { route?: unknown; link?: unknown } | null | undefined): string {
  if (typeof data?.route === "string" && /^\/(?![/\\])[^\s\\]*$/.test(data.route)) return data.route; // in-app routes only
  const link = typeof data?.link === "string" ? data.link : "";
  const path = link.replace(/^https?:\/\/[^/]+/, "").split("?")[0];
  let m = path.match(/^\/orders\/([^/#]+)\/unboxing/);
  if (m) return `/unboxing/${m[1]}`;
  m = path.match(/^\/orders\/([^/#]+)/);
  if (m) return `/order/${m[1]}`;
  m = path.match(/^\/p\/([^/#]+)/);
  if (m) return `/product/${m[1]}`;
  m = path.match(/^\/group\/([^/#]+)/);
  if (m) return `/group/${m[1]}`;
  if (path === "/cart") return "/cart";
  if (path.startsWith("/account/points")) return "/points";
  if (path.startsWith("/account/wallet")) return "/wallet";
  return "/notifications";
}

export function openFromNotification(data: { route?: unknown; link?: unknown } | null | undefined) {
  router.push(routeFor(data) as never);
}

let handledColdStart = false;

/** Tap on a push (also the one that cold-started the app) → deep link. Returns an unsubscribe. */
export function listenForTaps(): () => void {
  if (Platform.OS === "web") return () => {};
  if (!handledColdStart) {
    handledColdStart = true;
    Notifications.getLastNotificationResponseAsync()
      .then((r) => {
        if (!r) return;
        // Let the navigator mount before pushing a screen.
        setTimeout(() => openFromNotification(r.notification.request.content.data), 300);
        Notifications.clearLastNotificationResponseAsync?.().catch(() => {});
      })
      .catch(() => {});
  }
  const sub = Notifications.addNotificationResponseReceivedListener((r) => openFromNotification(r.notification.request.content.data));
  return () => sub.remove();
}
