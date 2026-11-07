"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled } from "@/lib/apiClient";
import { money, PERIOD_LABEL, type MoneyQuote } from "@/lib/money";
import { formatBdt } from "@/lib/pricing";
import type { CartItem } from "@/lib/types";
import "./money.css";

/** Cart: "You'd save ৳X with Plus" for non-members, "Plus saved you ৳X" for members (from POST /checkout/quote). */
export function CartPlusNudge({ cart, coupon }: { cart: CartItem[]; coupon?: string }) {
  const on = useFeature("membership");
  const [q, setQ] = useState<Pick<MoneyQuote, "membership" | "plusPreview"> | null>(null);
  const key = cart.map((i) => `${i.productId}:${i.variant ?? ""}:${i.qty}`).join("|") + "|" + (coupon ?? "");

  useEffect(() => {
    if (!on || !apiEnabled || !cart.length) return;
    let live = true;
    const t = setTimeout(() => {
      money
        .quote(cart, { coupon })
        .then((r) => live && setQ({ membership: r.membership, plusPreview: r.plusPreview }))
        .catch(() => live && setQ(null));
    }, 400);
    return () => {
      live = false;
      clearTimeout(t);
    };
    // key captures cart + coupon changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [on, key]);

  if (!q) return null;
  if (q.membership && q.membership.savedBdt > 0)
    return (
      <p className="money-in flex items-center gap-2 rounded-2xl bg-good/10 p-3 text-xs font-semibold text-good">
        <span className="plus-badge shrink-0">PLUS</span> Plus saves you {formatBdt(q.membership.savedBdt)} on this order
      </p>
    );
  if (!q.plusPreview) return null;
  const p = q.plusPreview;
  return (
    <Link href="/plus" className="money-in group flex min-h-11 items-center gap-3 rounded-2xl bg-surface-2 p-3 text-xs transition-transform active:scale-[0.99]">
      <span className="plus-badge shrink-0">PLUS</span>
      <span className="min-w-0 flex-1 text-ink-2">
        <span className="block text-sm font-bold text-ink">You&apos;d save {formatBdt(p.savedBdt)} with Plus</span>
        {p.plan.name} from {formatBdt(p.plan.priceBdt)}/{PERIOD_LABEL[p.plan.period]}
      </span>
      <Icon name="right" size={16} className="shrink-0 text-ink-3 transition-transform group-hover:translate-x-0.5" />
    </Link>
  );
}
