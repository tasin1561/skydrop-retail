"use client";

import Link from "next/link";
import { storeText } from "@/lib/markets";
import { useT } from "@/i18n/client";
import type { MarketId, StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";
import { StoreName } from "../store/StoreName";

type Scene = { mode: StoreMode; images: (string | null)[] };

/**
 * Calm, light hero: headline, one-line value proposition, a primary (ink) and a secondary (outline) action and
 * three quiet trust facts; on wide screens a single neutral image well on the right. No store tabs here: the
 * header's "Shopping at" row already switches stores.
 */
export function StoreHero({ scenes, fixed }: { scenes: Scene[]; fixed?: boolean }) {
  const { enabled } = useStore();
  const { t } = useT();
  const list = scenes.filter((s) => s.mode === "all" || enabled[s.mode as MarketId]);
  const scene = list[0] ?? scenes[0];
  const info = storeText(scene.mode, t);
  const img = scene.images.find(Boolean) ?? null;

  return (
    <section className="mx-4 overflow-hidden rounded-[12px] border border-line bg-surface sm:mx-0">
      <div className={`grid gap-6 ${fixed ? "p-5" : "p-6"} sm:p-8 md:grid-cols-[1.3fr_1fr] md:items-center lg:px-12 lg:py-10`}>
        <div className="min-w-0">
          {scene.mode === "all" ? (
            <p className="text-[13px] text-ink-3">Skydrop Retail</p>
          ) : (
            <p className="flex flex-wrap items-baseline gap-x-1.5 text-[13px] text-ink-3">
              <StoreName mode={scene.mode} size="text-sm" />
              <span>· {t("home.sourcedFrom", { source: info.source })}</span>
            </p>
          )}
          <h1 className={`${fixed ? "mt-1.5 text-[22px]" : "mt-2 text-[26px]"} max-w-xl font-semibold leading-[1.2] tracking-[-0.02em] text-ink sm:text-[30px] lg:text-[32px]`}>
            {info.heroTitle}
          </h1>
          <p className={`mt-3 max-w-md text-[15px] leading-relaxed text-ink-2 ${fixed ? "hidden sm:block" : ""}`}>{info.heroText}</p>
          <div className={`${fixed ? "mt-4" : "mt-6"} flex flex-wrap gap-2`}>
            <Link href={scene.mode === "all" ? "/search?q=&store=all" : `/store/${scene.mode}`} className="btn btn-primary">
              {scene.mode === "all" ? t("hero.start") : t("hero.shop", { store: info.name })} <Icon name="arrow" size={16} className="arrow" />
            </Link>
            <Link href="/how-it-works" className={`btn btn-outline ${fixed ? "hidden sm:inline-flex" : ""}`}>
              {t("hero.how")}
            </Link>
          </div>
          <ul className={`mt-6 flex-wrap gap-x-5 gap-y-2 text-[13px] text-ink-3 ${fixed ? "hidden sm:flex" : "flex"}`}>
            <li className="inline-flex items-center gap-1.5">
              <Icon name="truck" size={15} /> {t("hero.days")}
            </li>
            <li className="inline-flex items-center gap-1.5">
              <Icon name="shield" size={15} /> {t("hero.verified")}
            </li>
            <li className="inline-flex items-center gap-1.5">
              <Icon name="card" size={15} /> {t("footer.badge.pay")}
            </li>
          </ul>
        </div>

        {!fixed && (
          <div aria-hidden className="img-well hidden h-[280px] place-items-center overflow-hidden rounded-[10px] bg-surface-2 p-8 md:grid">
            {img ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={img} alt="" className="h-full w-full object-contain" />
            ) : (
              <Icon name="package" size={72} stroke={1.2} className="text-ink-3" />
            )}
          </div>
        )}
      </div>
    </section>
  );
}
