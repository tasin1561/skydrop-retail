// Affiliate (?ref=) and refer-a-friend (?invite=) codes captured from deep links. Kept free of API imports so
// src/api/client.ts can read them without an import cycle.
import AsyncStorage from "@react-native-async-storage/async-storage";

export type Kind = "ref" | "invite";

const DAYS = 30;
const KEYS: Record<Kind, string> = { ref: "sr.growth.ref", invite: "sr.growth.invite" };
const RULES: Record<Kind, RegExp> = { ref: /^[A-Z0-9]{4,16}$/, invite: /^[A-Z0-9]{4,12}$/ };

/** Saves a code (last one wins, 30 days). Returns the normalised code, or null when invalid. */
export async function saveCode(kind: Kind, raw: unknown): Promise<string | null> {
  const code = String(Array.isArray(raw) ? raw[0] : (raw ?? "")).trim().toUpperCase();
  if (!RULES[kind].test(code)) return null;
  try {
    await AsyncStorage.setItem(KEYS[kind], JSON.stringify({ code, exp: Date.now() + DAYS * 86_400_000 }));
  } catch {
    /* ignore */
  }
  return code;
}

export async function storedCode(kind: Kind): Promise<string | undefined> {
  try {
    const raw = await AsyncStorage.getItem(KEYS[kind]);
    if (!raw) return undefined;
    const v = JSON.parse(raw) as { code: string; exp: number };
    return v.exp > Date.now() ? v.code : undefined;
  } catch {
    return undefined;
  }
}
