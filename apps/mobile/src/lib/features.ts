// Feature switches from Admin → System → Features (GET /api/v1/features), fetched once at startup.
// Demo mode / unreachable API: everything on except paid / opt-in features (mirrors App\Support\Features::REGISTRY).
import { useSyncExternalStore } from "react";
import { http, isDemo, resolveMode } from "@/api/client";

export type Features = Record<string, boolean>;

const DEFAULTS: Features = { "pay.emi": false, membership: false };
let features: Features = {};
let loading: Promise<Features> | null = null;
const subs = new Set<() => void>();

/** Loads the switches once (later calls reuse the first request). */
export function loadFeatures(): Promise<Features> {
  loading ??= (async () => {
    try {
      await resolveMode();
      if (!isDemo()) features = (await http<{ features: Features }>("GET", "/features")).features ?? {};
    } catch {
      /* keep defaults */
    }
    subs.forEach((f) => f());
    return features;
  })();
  return loading;
}

export function isOn(key: string) {
  return features[key] ?? DEFAULTS[key] ?? true;
}

const subscribe = (f: () => void) => {
  subs.add(f);
  return () => {
    subs.delete(f);
  };
};

export function useFeature(key: string) {
  return useSyncExternalStore(subscribe, () => isOn(key), () => isOn(key));
}
