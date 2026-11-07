import { Icon } from "../Icon";
import { getT } from "@/i18n/server";

export async function PromoStrip() {
  const { t } = await getT();
  const items = [
    { icon: "tag", title: "SKYDROP10", text: t("promo.first") },
    { icon: "truck", title: t("promo.freeShip"), text: t("promo.freeShipText") },
    { icon: "shield", title: t("promo.pricePromise"), text: t("promo.pricePromiseText") },
    { icon: "wallet", title: t("promo.payWay"), text: t("promo.payWayText") },
  ];
  return (
    <section className="no-scrollbar mt-6 flex gap-3 overflow-x-auto px-4 sm:grid sm:grid-cols-4 sm:px-0">
      {items.map((it, i) => (
        <div key={it.title} className="card rise flex min-w-[220px] items-center gap-3 p-3 sm:min-w-0" style={{ ["--i" as string]: i }}>
          <span className="chip-icon shrink-0">
            <Icon name={it.icon} size={18} />
          </span>
          <div>
            <p className="text-sm font-bold">{it.title}</p>
            <p className="text-xs text-ink-2">{it.text}</p>
          </div>
        </div>
      ))}
    </section>
  );
}
