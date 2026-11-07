"use client";

import { useEffect } from "react";
import { productItem, track } from "@/lib/track";

export type TrackProduct = { id: string; title: string; brand?: string; category?: string; priceBdt: number };

/** ViewContent once per product page view. Renders nothing. */
export function TrackView({ product }: { product: TrackProduct }) {
  const { id, title, brand, category, priceBdt } = product;
  useEffect(() => {
    track("ViewContent", { items: [productItem({ id, title, brand, category, priceBdt })], value: priceBdt });
  }, [id, title, brand, category, priceBdt]);
  return null;
}
