"use client";

import Link from "next/link";
import type { MarketId, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";
import { ProductGrid, Rail } from "../ProductBits";

// Hides products from stores the admin has switched off.
export function EnabledRail(props: { title: string; href?: string; note?: string; items: Product[] }) {
  const { enabled } = useStore();
  return <Rail {...props} items={props.items.filter((p) => enabled[p.market])} />;
}

export function EnabledGrid({ items }: { items: Product[] }) {
  const { enabled } = useStore();
  return <ProductGrid items={items.filter((p) => enabled[p.market])} />;
}

export function StoreGate({ market, children }: { market: MarketId; children: React.ReactNode }) {
  const { enabled, ready } = useStore();
  if (ready && !enabled[market]) {
    return (
      <div className="mx-auto max-w-md px-4 py-20 text-center">
        <span className="chip-icon mx-auto h-14 w-14">
          <Icon name="clock" size={26} />
        </span>
        <h1 className="mt-4 text-xl font-extrabold">{STORES[market].name} is resting</h1>
        <p className="mt-2 text-sm text-ink-2">
          Shopping from {STORES[market].source} is switched off right now. Your other stores are open.
        </p>
        <Link href="/" className="btn btn-primary mt-6">
          Back to all stores <Icon name="arrow" size={18} className="arrow" />
        </Link>
      </div>
    );
  }
  return <>{children}</>;
}
