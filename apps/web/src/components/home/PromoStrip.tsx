import { Icon } from "../Icon";
import { getT } from "@/i18n/server";
import { featuredCoupons } from "@/lib/featuredCoupons";
import { couponText } from "@/lib/coupons";
import type { StoreMode } from "@/lib/types";

const COLS = ["", "sm:grid-cols-1", "sm:grid-cols-2", "sm:grid-cols-3", "sm:grid-cols-4", "sm:grid-cols-5"];

/** Coupon cards come from the admin (featured coupons); the card is hidden when none is live. */
export async function PromoStrip({ store = "all" }: { store?: StoreMode }) {
  const tr = await getT();
  const { t } = tr;
  const coupons = (await featuredCoupons(store)).slice(0, 2);
  const items = [
    ...coupons.map((c) => ({ key: "c-" + c.code, icon: "tag", title: c.code, text: couponText(c, tr) })),
    { key: "ship", icon: "truck", title: t("promo.freeShip"), text: t("promo.freeShipText") },
    { key: "price", icon: "shield", title: t("promo.pricePromise"), text: t("promo.pricePromiseText") },
    { key: "pay", icon: "wallet", title: t("promo.payWay"), text: t("promo.payWayText") },
  ];
  return (
    <section className={"no-scrollbar mt-6 flex gap-3 overflow-x-auto px-4 sm:grid sm:px-0 " + COLS[items.length]}>
      {items.map((it, i) => (
        <div key={it.key} className="card rise flex min-w-[220px] items-center gap-3 p-3 sm:min-w-0" style={{ ["--i" as string]: i }}>
          <span className="chip-icon shrink-0">
            <Icon name={it.icon} size={18} />
          </span>
          <div className="min-w-0">
            <p className="text-sm font-bold">{it.title}</p>
            <p className="text-xs text-ink-2">{it.text}</p>
          </div>
        </div>
      ))}
    </section>
  );
}
