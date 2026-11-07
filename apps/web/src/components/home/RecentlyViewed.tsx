"use client";

import { useEffect, useState } from "react";
import type { Product } from "@/lib/types";
import { recentlyViewedIds } from "../product/ProductExtras";
import { EnabledRail } from "./Gates";

export function RecentlyViewed() {
  const [items, setItems] = useState<Product[]>([]);
  useEffect(() => {
    const ids = recentlyViewedIds().slice(0, 12);
    if (!ids.length) return;
    fetch(`/api/products?ids=${ids.map(encodeURIComponent).join(",")}`)
      .then((r) => r.json())
      .then((d: { items?: Product[] }) => setItems(d.items ?? []))
      .catch(() => {});
  }, []);
  if (items.length < 2) return null;
  return <EnabledRail title="Recently viewed" note="Pick up where you left off" items={items} />;
}
