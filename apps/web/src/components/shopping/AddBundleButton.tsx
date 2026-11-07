"use client";

import { useRouter } from "next/navigation";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { formatBdt } from "@/lib/pricing";
import { cartLine } from "@/lib/shopping/cart";
import type { Bundle } from "@/lib/shopping/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";

/** Adds every bundle item to the cart; the bundle saving is applied in the cart and at checkout. */
export function AddBundleButton({ b }: { b: Bundle }) {
  const { addToCart } = useStore();
  const router = useRouter();
  const toast = useToast();
  const { t, bdt } = useT();
  return (
    <button
      type="button"
      className="btn btn-primary min-h-12 w-full sm:w-auto"
      disabled={!b.available || !b.items?.length}
      onClick={() => {
        for (const i of b.items ?? []) addToCart(cartLine(i.product, i.qty));
        toast({ kind: "success", title: t("bundle.added"), body: t("bundle.saveAtCheckout", { amount: bdt(b.discountBdt) }) });
        router.push("/cart");
      }}
    >
      <Icon name="cart" size={18} /> {b.available ? t("bundle.add", { amount: bdt(b.totalBdt) }) : t("bundle.oos")}
    </button>
  );
}
