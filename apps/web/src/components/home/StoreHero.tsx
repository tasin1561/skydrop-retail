"use client";

import Link from "next/link";
import { useState } from "react";
import { STORES, storeText } from "@/lib/markets";
import { useT } from "@/i18n/client";
import type { MarketId, StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";

type Scene = { mode: StoreMode; images: (string | null)[] };

// Scene switcher hero: store tabs swap the visual, title and tint together. Manual only (no auto-rotate).
export function StoreHero({ scenes, fixed }: { scenes: Scene[]; fixed?: boolean }) {
  const { enabled } = useStore();
  const { t } = useT();
  const list = scenes.filter((s) => s.mode === "all" || enabled[s.mode as MarketId]);
  const [idx, setIdx] = useState(0);
  const scene = list[Math.min(idx, list.length - 1)] ?? scenes[0];
  const info = storeText(scene.mode, t);

  return (
    <section
      data-store={scene.mode}
      className="relative mx-4 overflow-hidden rounded-[16px] text-white sm:mx-0"
    >
      {/* pre-painted tint layers cross-faded with opacity */}
      {list.map((s) => (
        <div
          key={s.mode}
          data-store={s.mode}
          aria-hidden
          className="absolute inset-0 transition-opacity duration-200"
          style={{
            opacity: s.mode === scene.mode ? 1 : 0,
            // flat, deep store tint with a restrained accent edge (no blobs, no glow)
            background: "linear-gradient(135deg, var(--hero-a) 0%, var(--hero-a) 55%, color-mix(in srgb, var(--hero-a) 72%, var(--hero-b)) 100%)",
          }}
        />
      ))}

      <div className={`relative grid gap-6 ${fixed ? "p-4" : "p-5"} sm:p-8 md:grid-cols-[1.15fr_1fr] md:items-center lg:p-10`}>
        <div key={scene.mode} className="fade-in">
          <span className="inline-flex items-center gap-1.5 rounded-md border border-white/20 px-2 py-1 text-xs font-medium text-white/90">
            <Icon name={info.icon} size={14} stroke={2} />
            {scene.mode === "all" ? "Skydrop Retail" : t("hero.sourced", { store: info.name, source: info.source })}
          </span>
          <h1 className={`${fixed ? "mt-2 text-[20px]" : "mt-3 text-[26px]"} font-bold leading-[1.15] tracking-tight sm:text-4xl lg:text-[44px]`}>{info.heroTitle}</h1>
          <p className={`mt-3 max-w-md text-sm text-white/85 sm:text-base ${fixed ? "hidden sm:block" : ""}`}>{info.heroText}</p>
          <div className={`${fixed ? "mt-3" : "mt-5"} flex flex-wrap gap-2`}>
            <Link href={scene.mode === "all" ? "/search?q=&store=all" : `/store/${scene.mode}`} className="btn bg-white text-[color:var(--hero-a)] hover:bg-white/90">
              {scene.mode === "all" ? t("hero.start") : t("hero.shop", { store: info.name })} <Icon name="arrow" size={18} className="arrow" />
            </Link>
            <Link href="/how-it-works" className={`btn border border-white/40 text-white hover:bg-white/10 ${fixed ? "hidden sm:inline-flex" : ""}`}>
{t("hero.how")}
</Link>
          </div>
          {/* phones: a small fanned stack in its own row, clipped tiles, so nothing overlaps the copy */}
          <div className={`mt-6 items-center gap-2 md:hidden ${fixed ? "hidden" : "flex"}`} aria-hidden>
            {scene.images.slice(0, 3).map((src, i) =>
              src ? (
                <span
                  key={i}
                  className="grid h-[72px] w-[72px] shrink-0 place-items-center overflow-hidden rounded-[10px] bg-white p-2 ring-1 ring-white/15"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={src} alt="" className="max-h-full max-w-full object-contain" />
                </span>
              ) : null,
            )}
          </div>
          <div className={`mt-5 flex-wrap gap-x-4 gap-y-1.5 text-xs text-white/85 ${fixed ? "hidden sm:flex" : "flex"}`}>
            <span className="inline-flex items-center gap-1">
              <Icon name="truck" size={14} /> {t("hero.days")}
            </span>
            <span className="inline-flex items-center gap-1">
              <Icon name="shield" size={14} /> {t("hero.verified")}
            </span>
          </div>
        </div>

        <div key={scene.mode + "-art"} className="fade-in relative hidden h-64 md:block lg:h-72">
          {scene.images.slice(0, 3).map((src, i) => (
            <div
              key={i}
              className="absolute grid place-items-center overflow-hidden rounded-2xl bg-white p-3 ring-1 ring-white/10"
              style={{
                width: i === 0 ? "52%" : "36%",
                aspectRatio: "1",
                left: ["24%", "0%", "62%"][i],
                top: ["4%", "40%", "46%"][i],
                zIndex: i === 0 ? 2 : 1,
              }}
            >
              {src ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={src} alt="" className="h-full w-full object-contain" />
              ) : (
                <Icon name={["basket", "package", "tag"][i]} size={64} className="text-[color:var(--hero-a)]" stroke={1.3} />
              )}
            </div>
          ))}
        </div>
      </div>

      {!fixed && list.length > 1 && (
        <div role="tablist" aria-label={t("hero.featured")} className="relative flex gap-2 overflow-x-auto px-5 pb-5 no-scrollbar sm:px-8">
          {list.map((s, i) => (
            <button
              key={s.mode}
              role="tab"
              aria-selected={s.mode === scene.mode}
              onClick={() => setIdx(i)}
              className={
                "flex min-h-10 shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition sm:min-h-0 " +
                (s.mode === scene.mode ? "bg-white text-[color:var(--hero-a)]" : "bg-white/15 text-white hover:bg-white/25")
              }
            >
              <Icon name={STORES[s.mode].icon} size={14} stroke={2} />
              {s.mode === "all" ? t("store.all.name") : STORES[s.mode].name}
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
