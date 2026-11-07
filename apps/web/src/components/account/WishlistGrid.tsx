"use client";

import { useEffect, useState } from "react";
import type { Product } from "@/lib/types";
import { useStore } from "@/state/store";
import { ProductCard } from "@/components/ProductBits";
import { Icon } from "@/components/Icon";
import { EmptyState, PageHead } from "./ui";
import { ShareWishlist } from "@/components/share/ShareWishlist";

type Load = { key: string; items: Product[]; error: boolean };

export function WishlistGrid() {
  const { ready, wishlist } = useStore();
  const [data, setData] = useState<Load>({ key: "", items: [], error: false });
  const [retry, setRetry] = useState(0);
  // Product data is fetched only when an id has no data yet; removing a heart filters locally.
  const key = [...wishlist].sort().join(",");
  const missing = wishlist.some((id) => !data.items.find((p) => p.id === id));
  const need = ready && missing ? key + "#" + retry : null;

  useEffect(() => {
    if (!need) return;
    const ids = need.split("#")[0];
    const ctrl = new AbortController();
    fetch(`/api/products?ids=${encodeURIComponent(ids)}`, { signal: ctrl.signal })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((j: { items: Product[] }) => setData({ key: need, items: j.items, error: false }))
      .catch((e) => {
        if ((e as Error).name !== "AbortError") setData((d) => ({ ...d, key: need, error: true }));
      });
    return () => ctrl.abort();
  }, [need]);

  const loading = !ready || (need !== null && data.key !== need);
  const failed = need !== null && data.key === need && data.error;
  // Keep the shopper's order: most recently saved first.
  const shown = [...wishlist]
    .reverse()
    .map((id) => data.items.find((p) => p.id === id))
    .filter((p): p is Product => !!p);
  const gone = ready && !loading && !failed ? wishlist.length - shown.length : 0;

  return (
    <div className="mx-auto max-w-7xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead
        title="Wishlist"
        sub={ready ? `${String(wishlist.length).padStart(2, "0")} saved item${wishlist.length === 1 ? "" : "s"}` : "Saved for later"}
        icon="heart"
        back={{ href: "/account", label: "Account" }}
      />
      {!loading && shown.length > 0 && <ShareWishlist ids={[...wishlist].reverse()} />}
      <p className="sr-only" aria-live="polite">
        {loading ? "Loading wishlist" : `${shown.length} items in your wishlist`}
      </p>

      {loading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5" aria-busy="true">
          {Array.from({ length: Math.min(Math.max(wishlist.length, 2), 10) }, (_, i) => (
            <div key={i} className="skeleton aspect-[3/5] rounded-2xl" />
          ))}
        </div>
      ) : failed ? (
        <div className="card mx-auto flex max-w-md flex-col items-center p-8 text-center">
          <span className="chip-icon" style={{ background: "color-mix(in srgb, var(--bad) 12%, transparent)", color: "var(--bad)" }}>
            <Icon name="info" size={18} />
          </span>
          <p className="mt-3 font-bold">Couldn&apos;t load your saved items</p>
          <p className="text-sm text-ink-2">Check your connection and try again.</p>
          <button type="button" className="btn btn-primary mt-4" onClick={() => setRetry((r) => r + 1)}>
            <Icon name="refresh" size={16} /> Try again
          </button>
        </div>
      ) : shown.length === 0 ? (
        <EmptyState
          icon="heart"
          title="Your wishlist is empty"
          body="Tap the heart on any product to save it here and keep an eye on the price in taka."
          action={{ label: "Discover products", href: "/" }}
        />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            {shown.map((p, i) => (
              <ProductCard key={p.id} p={p} i={i} />
            ))}
          </div>
          {gone > 0 && (
            <p className="mt-4 flex items-center gap-2 text-xs text-ink-2">
              <Icon name="info" size={14} /> {gone} saved item{gone === 1 ? " is" : "s are"} no longer available.
            </p>
          )}
        </>
      )}
    </div>
  );
}
