"use client";

import { createContext, useContext, type ReactNode } from "react";
import { DEFAULT_FEATURES, isOn, type FeatureKey, type Features } from "@/lib/features";

const Ctx = createContext<Features>(DEFAULT_FEATURES);

export function FeaturesProvider({ value, children }: { value: Features; children: ReactNode }) {
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useFeatures() {
  return useContext(Ctx);
}

export function useFeature(key: FeatureKey) {
  return isOn(useContext(Ctx), key);
}

/** Renders children only when the feature is on. */
export function Feature({ name, children, fallback = null }: { name: FeatureKey; children: ReactNode; fallback?: ReactNode }) {
  return useFeature(name) ? children : fallback;
}
