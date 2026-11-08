"use client";

import Link from "next/link";
import { MARKET_IDS, storeText } from "@/lib/markets";
import { useT } from "@/i18n/client";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";
import { StoreName } from "../store/StoreName";

export function StoreCards() {
  const { enabled } = useStore();
  const { t } = useT();
  const list = MARKET_IDS.filter((m) => enabled[m]);
  return (
    <section className="mt-8 px-4 sm:px-0" aria-labelledby="stores">
      <h2 id="stores" className="text-lg font-semibold tracking-[-0.01em] sm:text-xl">
        {t("home.whereShop")}
      </h2>
      <p className="text-sm text-ink-2">{t("home.whereShopNote")}</p>
      <div className="no-scrollbar snap-x-rail -mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0">
        {list.map((m, i) => {
          const s = storeText(m, t);
          return (
            <Link
              key={m}
              href={`/store/${m}`}
              data-store={m}
              className="rise group relative flex w-[78vw] max-w-[320px] shrink-0 flex-col overflow-hidden rounded-[12px] border border-line bg-surface p-5 transition-colors sm:w-auto sm:max-w-none hover:border-line-2"
              style={{ ["--i" as string]: i }}
            >
              <h3>
                <StoreName mode={m} variant="hero" source size="text-[22px]" />
              </h3>
              <p className="mt-2 line-clamp-2 text-sm text-ink-2">{s.tagline}</p>
              <span className="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-medium text-ink group-hover:underline group-hover:underline-offset-4">
                {t("home.enterStore")} <Icon name="arrow" size={15} className="transition-transform group-hover:translate-x-0.5" />
              </span>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
