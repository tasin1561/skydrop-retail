// Feature switches from Admin → System → Features (GET /api/v1/features), fetched once at startup.
// Demo mode / unreachable API: everything on except paid / opt-in features (mirrors App\Support\Features::REGISTRY).
import { useSyncExternalStore } from "react";
import { http, isDemo, resolveMode } from "@/api/client";

export type Features = Record<string, boolean>;

/** Storefront maintenance (Admin → System → Settings), from the same GET /features response. */
export type Maintenance = { on: boolean; message: string; since?: string | null };

const DEFAULTS: Features = { "pay.emi": false, membership: false };
const NO_MAINTENANCE: Maintenance = { on: false, message: "" };
let features: Features = {};
let maintenance: Maintenance = NO_MAINTENANCE;
let loading: Promise<Features> | null = null;
const subs = new Set<() => void>();

/** Loads the switches once (later calls reuse the first request). */
export function loadFeatures(): Promise<Features> {
  loading ??= (async () => {
    try {
      await resolveMode();
      if (!isDemo()) {
        const res = await http<{ features: Features; maintenance?: Maintenance }>("GET", "/features");
        features = res.features ?? {};
        maintenance = res.maintenance ?? NO_MAINTENANCE;
      }
    } catch {
      /* keep defaults */
    }
    subs.forEach((f) => f());
    return features;
  })();
  return loading;
}

/** Fetches the switches again (maintenance screen "Try again"). */
export function reloadFeatures(): Promise<Features> {
  loading = null;
  return loadFeatures();
}

export function getMaintenance() {
  return maintenance;
}

export function useMaintenance(): Maintenance {
  return useSyncExternalStore(subscribe, getMaintenance, getMaintenance);
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
