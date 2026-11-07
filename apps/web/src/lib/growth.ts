// Growth module (affiliate program, refer a friend, sharing). Browser-only helpers: every storage access is
// guarded so these are safe to import from client components that also render on the server.
import { apiEnabled, apiRequest, API_URL } from "./apiClient";
import type { Product } from "./types";

/* ---------------- Codes captured from links ---------------- */

const REF_KEY = "skydrop-retail:ref";
const INVITE_KEY = "skydrop-retail:invite";
const REF_COOKIE = "sr_ref";
const INVITE_COOKIE = "sr_invite";
export const DEFAULT_COOKIE_DAYS = 30;

type Stored = { code: string; at: number; exp: number };

export const normalizeRef = (c?: string | null) => {
  const v = (c ?? "").trim().toUpperCase();
  return /^[A-Z0-9]{4,16}$/.test(v) ? v : null;
};
export const normalizeInvite = (c?: string | null) => {
  const v = (c ?? "").trim().toUpperCase();
  return /^[A-Z0-9]{4,12}$/.test(v) ? v : null;
};

function write(key: string, cookie: string, code: string, days: number) {
  const now = Date.now();
  const value: Stored = { code, at: now, exp: now + days * 86_400_000 };
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* storage blocked: the cookie still works */
  }
  try {
    document.cookie = `${cookie}=${code}; Max-Age=${days * 86_400}; Path=/; SameSite=Lax`;
  } catch {
    /* ignore */
  }
}

function read(key: string, cookie: string): string | undefined {
  try {
    const raw = localStorage.getItem(key);
    if (raw) {
      const v = JSON.parse(raw) as Stored;
      if (v.exp > Date.now()) return v.code;
      localStorage.removeItem(key);
    }
  } catch {
    /* fall through to the cookie */
  }
  try {
    const m = document.cookie.match(new RegExp(`(?:^|; )${cookie}=([A-Z0-9]+)`));
    return m?.[1];
  } catch {
    return undefined;
  }
}

/** Affiliate code: last click wins, remembered for cookie_days (30 by default). */
export function saveRef(code: string, days = DEFAULT_COOKIE_DAYS) {
  write(REF_KEY, REF_COOKIE, code, days);
}
export const getRef = () => (typeof window === "undefined" ? undefined : read(REF_KEY, REF_COOKIE));

/** Refer-a-friend code, sent once at OTP verify for new accounts. */
export function saveInvite(code: string) {
  write(INVITE_KEY, INVITE_COOKIE, code, DEFAULT_COOKIE_DAYS);
}
export const getInvite = () => (typeof window === "undefined" ? undefined : read(INVITE_KEY, INVITE_COOKIE));
export function clearInvite() {
  try {
    localStorage.removeItem(INVITE_KEY);
    document.cookie = `${INVITE_COOKIE}=; Max-Age=0; Path=/; SameSite=Lax`;
  } catch {
    /* ignore */
  }
}

/** Adds ?ref=CODE (approved affiliate) or else ?invite=CODE (signed in) to a link on our site. */
export function withCode(url: string, codes: ShareCodes): string {
  try {
    const u = new URL(url);
    u.searchParams.delete("ref");
    u.searchParams.delete("invite");
    if (codes.ref) u.searchParams.set("ref", codes.ref);
    else if (codes.invite) u.searchParams.set("invite", codes.invite);
    return u.toString();
  } catch {
    return url;
  }
}

/** Turns a pasted storefront URL (or path) into a path on this site, or null when it is not ours. */
export function sitePath(input: string, origin: string): string | null {
  const raw = input.trim();
  if (!raw) return null;
  try {
    const u = new URL(raw.startsWith("/") ? origin + raw : /^https?:\/\//i.test(raw) ? raw : "https://" + raw);
    const o = new URL(origin);
    const ours = u.host === o.host || u.host.replace(/^www\./, "") === o.host.replace(/^www\./, "") || u.host.endsWith("skydrop.online");
    if (!ours) return null;
    u.searchParams.delete("ref");
    u.searchParams.delete("invite");
    return u.pathname + (u.search || "");
  } catch {
    return null;
  }
}

/* ---------------- API ---------------- */

export type ProgramRules = {
  defaultRate: number;
  rates: Record<string, number>;
  cookieDays: number;
  holdDays: number;
  minPayoutBdt: number;
  maxPerOrderBdt: number;
  payoutMethods: ("bkash" | "nagad")[];
};

export type AffiliateStatus = "pending" | "approved" | "suspended";

export type AffiliateMe = {
  affiliate: null | {
    code: string;
    status: AffiliateStatus;
    rate: number | null;
    payoutMethod: "bkash" | "nagad" | null;
    payoutNumber: string | null;
    codeEditable: boolean;
    link: string;
    createdAt?: string;
  };
  stats?: {
    clicks: number;
    clicks30d: number;
    orders: number;
    conversion: number;
    pendingBdt: number;
    approvedBdt: number;
    paidBdt: number;
    availableBdt: number;
    requestedBdt: number;
  };
  commissions?: { id: number; order: string; baseBdt: number; rate: number; amountBdt: number; status: "pending" | "approved" | "reversed" | "paid"; approveAfter?: string | null; at: string }[];
  payouts?: { id: number; amountBdt: number; method: string; status: "requested" | "paid" | "rejected"; reference?: string | null; requestedAt: string; paidAt?: string | null }[];
  program: ProgramRules;
};

export type ReferralMe = {
  code: string;
  link: string;
  invited: number;
  rewarded: number;
  pointsEarned: number;
  rules: { inviterPoints: number; friendPoints: number };
  friends: { name: string; joinedAt: string; status: "joined" | "rewarded" }[];
};

export type SharedWishlist = { code: string; owner: { name: string }; count: number; items: Product[] };

export const growthApi = {
  program: () => apiRequest.call<{ program: ProgramRules }>("/affiliate/program"),
  affiliate: () => apiRequest.call<AffiliateMe>("/affiliate/me"),
  apply: (body: { code?: string; payoutMethod?: string; payoutNumber?: string; about?: string }) => apiRequest.post<AffiliateMe>("/affiliate/apply", body),
  updateAffiliate: (body: { code?: string; payoutMethod?: string; payoutNumber?: string }) =>
    apiRequest.call<AffiliateMe>("/affiliate/me", { method: "PATCH", body: JSON.stringify(body) }),
  requestPayout: () => apiRequest.post<AffiliateMe & { payout: NonNullable<AffiliateMe["payouts"]>[number] }>("/affiliate/payouts", {}),
  referrals: () => apiRequest.call<ReferralMe>("/referrals/me"),
  shareWishlist: () => apiRequest.post<{ code: string; url: string }>("/wishlist/share", {}),
  click: (code: string, productId?: string, url?: string) =>
    fetch(`${API_URL}/r/click`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ code, productId, url }),
      keepalive: true,
    })
      .then((r) => (r.ok ? (r.json() as Promise<{ valid: boolean; cookieDays: number }>) : null))
      .catch(() => null),
};

/* ---------------- Demo mode (no NEXT_PUBLIC_API_URL) ---------------- */

export const DEMO_PROGRAM: ProgramRules = {
  defaultRate: 3,
  rates: { electronics: 2, fashion: 6, beauty: 5, home: 4, grocery: 1.5 },
  cookieDays: 30,
  holdDays: 7,
  minPayoutBdt: 500,
  maxPerOrderBdt: 1000,
  payoutMethods: ["bkash", "nagad"],
};

const DEMO_AFF_KEY = "skydrop-retail:demo-affiliate";

/** Stable 7 character code for a phone number, so demo invite links look real. */
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

type DemoAffiliate = NonNullable<AffiliateMe["affiliate"]>;

export function demoAffiliateRead(): DemoAffiliate | null {
  try {
    const raw = localStorage.getItem(DEMO_AFF_KEY);
    return raw ? (JSON.parse(raw) as DemoAffiliate) : null;
  } catch {
    return null;
  }
}
export function demoAffiliateWrite(a: DemoAffiliate) {
  try {
    localStorage.setItem(DEMO_AFF_KEY, JSON.stringify(a));
  } catch {
    /* ignore */
  }
  invalidateShareCodes();
}

export function demoAffiliateMe(a: DemoAffiliate | null): AffiliateMe {
  if (!a) return { affiliate: null, program: DEMO_PROGRAM };
  const day = 86_400_000;
  const now = Date.now();
  const rows: NonNullable<AffiliateMe["commissions"]> = [
    { id: 6, order: "SR-••••Q7", baseBdt: 4280, rate: 6, amountBdt: 257, status: "pending", approveAfter: null, at: new Date(now - day).toISOString() },
    { id: 5, order: "SR-••••K2", baseBdt: 12950, rate: 2, amountBdt: 259, status: "pending", approveAfter: new Date(now + 4 * day).toISOString(), at: new Date(now - 6 * day).toISOString() },
    { id: 4, order: "SR-••••8M", baseBdt: 6400, rate: 5, amountBdt: 320, status: "approved", at: new Date(now - 15 * day).toISOString() },
    { id: 3, order: "SR-••••ZD", baseBdt: 2150, rate: 3, amountBdt: 65, status: "reversed", at: new Date(now - 18 * day).toISOString() },
    { id: 2, order: "SR-••••4F", baseBdt: 9800, rate: 4, amountBdt: 392, status: "approved", at: new Date(now - 21 * day).toISOString() },
    { id: 1, order: "SR-••••TA", baseBdt: 15600, rate: 6, amountBdt: 936, status: "paid", at: new Date(now - 40 * day).toISOString() },
  ];
  const approved = a.status === "approved";
  return {
    affiliate: a,
    stats: approved
      ? { clicks: 412, clicks30d: 268, orders: 5, conversion: 1.2, pendingBdt: 516, approvedBdt: 712, paidBdt: 936, availableBdt: 712, requestedBdt: 0 }
      : { clicks: 0, clicks30d: 0, orders: 0, conversion: 0, pendingBdt: 0, approvedBdt: 0, paidBdt: 0, availableBdt: 0, requestedBdt: 0 },
    commissions: approved ? rows : [],
    payouts: approved ? [{ id: 1, amountBdt: 936, method: "bkash", status: "paid", reference: "BK7Q2X91", requestedAt: new Date(now - 33 * day).toISOString(), paidAt: new Date(now - 32 * day).toISOString() }] : [],
    program: DEMO_PROGRAM,
  };
}

export function demoReferralMe(phone: string, origin: string): ReferralMe {
  const code = demoInviteCode(phone);
  const day = 86_400_000;
  return {
    code,
    link: `${origin}/?invite=${code}`,
    invited: 3,
    rewarded: 1,
    pointsEarned: 200,
    rules: { inviterPoints: 200, friendPoints: 100 },
    friends: [
      { name: "Karim U.", joinedAt: new Date(Date.now() - 20 * day).toISOString(), status: "rewarded" },
      { name: "Nusrat J.", joinedAt: new Date(Date.now() - 6 * day).toISOString(), status: "joined" },
      { name: "Tanvir", joinedAt: new Date(Date.now() - day).toISOString(), status: "joined" },
    ],
  };
}

/** Demo wishlist links carry the product ids in the code: /w/d-<base64url ids>. */
export function demoWishlistCode(ids: string[]): string {
  const b = typeof btoa === "function" ? btoa(ids.slice(0, 40).join(",")) : Buffer.from(ids.slice(0, 40).join(",")).toString("base64");
  return "d-" + b.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}
export function demoWishlistIds(code: string): string[] {
  if (!code.startsWith("d-")) return [];
  try {
    const b = code.slice(2).replace(/-/g, "+").replace(/_/g, "/");
    return Buffer.from(b, "base64").toString("utf8").split(",").filter((x) => /^[\w-]{1,40}$/.test(x)).slice(0, 40);
  } catch {
    return [];
  }
}

/* ---------------- Share codes for the signed-in shopper ---------------- */

export type ShareCodes = { ref?: string; invite?: string };

const cache = new Map<string, Promise<ShareCodes>>();
const listeners = new Set<() => void>();

export function invalidateShareCodes() {
  cache.clear();
  listeners.forEach((l) => l());
}
export function onShareCodesChange(l: () => void) {
  listeners.add(l);
  return () => {
    listeners.delete(l);
  };
}

/** Resolves the codes to append to shared links: ref for an approved affiliate, else invite when signed in. */
export function loadShareCodes(user: { phone: string } | null, token: string | null): Promise<ShareCodes> {
  if (!user) return Promise.resolve({});
  if (!apiEnabled || !token) {
    const a = demoAffiliateRead();
    return Promise.resolve({ ref: a?.status === "approved" ? a.code : undefined, invite: demoInviteCode(user.phone) });
  }
  const key = token;
  let p = cache.get(key);
  if (!p) {
    p = Promise.allSettled([growthApi.affiliate(), growthApi.referrals()]).then(([aff, refs]) => ({
      ref: aff.status === "fulfilled" && aff.value.affiliate?.status === "approved" ? aff.value.affiliate.code : undefined,
      invite: refs.status === "fulfilled" ? refs.value.code : undefined,
    }));
    cache.set(key, p);
  }
  return p;
}
