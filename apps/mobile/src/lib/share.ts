// Sharing + affiliate/referral codes (growth module). Mirrors apps/web/src/lib/growth.ts:
// links get ?ref=CODE for an approved affiliate, otherwise ?invite=CODE when signed in.
import { Share } from "react-native";
import { isDemo, tokenStore } from "@/api/client";
import { saveCode, type Kind } from "./growthCodes";

export { storedCode } from "./growthCodes";

/** Public storefront used in shared links (EXPO_PUBLIC_WEB_URL). */
export const WEB_URL = (process.env.EXPO_PUBLIC_WEB_URL ?? "https://retail.skydrop.online").trim().replace(/\/+$/, "");
const RAW = (process.env.EXPO_PUBLIC_API_URL ?? "").trim().replace(/\/+$/, "");
const BASE = RAW ? RAW + "/api/v1" : "";

export type ShareCodes = { ref?: string; invite?: string };

/** Stores a code from a deep link (?ref= / ?invite=) and records affiliate clicks. */
export async function captureCode(kind: Kind, raw: unknown) {
  const code = await saveCode(kind, raw);
  if (code && kind === "ref" && BASE && !isDemo()) {
    fetch(BASE + "/r/click", {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ code, url: "app" }),
    }).catch(() => {});
  }
}

/** Same stable demo code as the web storefront. */
export function demoInviteCode(seed: string): string {
  const A = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  let h = 2166136261;
  for (const ch of seed) h = Math.imul(h ^ ch.charCodeAt(0), 16777619) >>> 0;
  let out = "";
  for (let i = 0; i < 7; i++) {
    out += A[h % A.length];
    h = Math.imul(h ^ (i + 0x9e37), 2654435761) >>> 0;
  }
  return out;
}

let cached: { token: string; codes: Promise<ShareCodes> } | null = null;

async function get<T>(path: string, token: string): Promise<T | null> {
  try {
    const r = await fetch(BASE + path, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` } });
    return r.ok ? ((await r.json()) as T) : null;
  } catch {
    return null;
  }
}

export async function shareCodes(user: { phone: string } | null): Promise<ShareCodes> {
  if (!user) return {};
  const token = await tokenStore.get();
  if (!BASE || isDemo() || !token) return { invite: demoInviteCode(user.phone) };
  if (cached?.token !== token) {
    cached = {
      token,
      codes: Promise.all([
        get<{ affiliate: { code: string; status: string } | null }>("/affiliate/me", token),
        get<{ code: string }>("/referrals/me", token),
      ]).then(([a, r]) => ({ ref: a?.affiliate?.status === "approved" ? a.affiliate.code : undefined, invite: r?.code })),
    };
  }
  return cached.codes;
}

export function withCode(path: string, codes: ShareCodes): string {
  const url = WEB_URL + path;
  const param = codes.ref ? `ref=${codes.ref}` : codes.invite ? `invite=${codes.invite}` : "";
  return param ? url + (url.includes("?") ? "&" : "?") + param : url;
}

/** Native share sheet for a page of the storefront. Returns false when the shopper dismissed it. */
export async function sharePage(opts: { path: string; title: string; user: { phone: string } | null }): Promise<boolean> {
  const url = withCode(opts.path, await shareCodes(opts.user));
  const r = await Share.share({ title: opts.title, message: `${opts.title} on Skydrop Retail\n${url}`, url }, { dialogTitle: "Share" });
  return r.action !== Share.dismissedAction;
}
