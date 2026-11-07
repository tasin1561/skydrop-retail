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
      <h2 id="stores" className="text-lg font-extrabold tracking-tight sm:text-xl">
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
              className="sn rise group relative flex w-[78vw] max-w-[320px] shrink-0 flex-col overflow-hidden rounded-3xl border border-line bg-surface p-4 sm:w-auto sm:max-w-none sm:p-5 shadow-[var(--shadow-1)] transition hover:-translate-y-0.5 hover:border-[color:color-mix(in_srgb,var(--accent)_45%,var(--line))] hover:shadow-[var(--shadow-2)]"
              style={{ ["--i" as string]: i, backgroundImage: "radial-gradient(120% 90% at 100% 0%, color-mix(in srgb, var(--sn-ring) 10%, transparent), transparent 60%)" }}
            >
              <h3>
                <StoreName mode={m} variant="hero" source />
              </h3>
              <p className="mt-2 line-clamp-2 text-sm text-ink-2">{s.tagline}</p>
              <span className="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-semibold text-[color:var(--sn-a)]">
                {t("home.enterStore")} <Icon name="arrow" size={16} className="transition-transform group-hover:translate-x-1" />
              </span>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
