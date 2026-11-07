"use client";

import { useEffect, useState } from "react";
import type { Product } from "@/lib/types";
import { recentlyViewedIds } from "../product/ProductExtras";
import { EnabledRail } from "./Gates";
import { useT } from "@/i18n/client";

export function RecentlyViewed() {
  const [items, setItems] = useState<Product[]>([]);
  const { t } = useT();
  useEffect(() => {
    const ids = recentlyViewedIds().slice(0, 12);
    if (!ids.length) return;
    fetch(`/api/products?ids=${ids.map(encodeURIComponent).join(",")}`)
      .then((r) => r.json())
      .then((d: { items?: Product[] }) => setItems(d.items ?? []))
      .catch(() => {});
  }, []);
  if (items.length < 2) return null;
  // arrives after the page has rendered, so it eases in instead of popping into place
  return (
    <div className="reveal-in">
      <EnabledRail title={t("home.recent")} note={t("home.recentNote")} items={items} />
    </div>
  );
}
