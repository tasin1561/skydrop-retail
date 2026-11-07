"use client";

import { apiEnabled } from "@/lib/apiClient";
import { demoWishlistCode, growthApi } from "@/lib/growth";
import { useStore } from "@/state/store";
import { ShareButton } from "./ShareButton";

/** "Share my wishlist": a public /w/{code} page listing the saved products. */
export function ShareWishlist({ ids }: { ids: string[] }) {
  const { token, user } = useStore();
  const first = user?.name.split(" ")[0];

  return (
    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
      <p className="min-w-0 text-sm text-ink-2">
        <span className="block font-bold text-ink">Planning a gift?</span>
        Share your list and let friends and family pick from it.
      </p>
      <ShareButton
        variant="ghost"
        label="Share my wishlist"
        title={first ? `${first}'s wishlist` : "My wishlist"}
        text={first ? `${first}'s wishlist on Skydrop Retail` : "My wishlist on Skydrop Retail"}
        qr
        resolvePath={async () => {
          if (apiEnabled && token) {
            const r = await growthApi.shareWishlist();
            return `/w/${r.code}`;
          }
          return `/w/${demoWishlistCode(ids)}`; // demo (or signed out): the ids travel in the link
        }}
      />
    </div>
  );
}
