"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { STORES } from "@/lib/markets";
import type { MarketId, StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";

type Scene = { mode: StoreMode; images: (string | null)[] };

// Scene switcher hero: a thumbnail rail swaps the big visual, title and tint together.
export function StoreHero({ scenes, fixed }: { scenes: Scene[]; fixed?: boolean }) {
  const { enabled } = useStore();
  const list = scenes.filter((s) => s.mode === "all" || enabled[s.mode as MarketId]);
  const [idx, setIdx] = useState(0);
  const [paused, setPaused] = useState(false);
  const scene = list[Math.min(idx, list.length - 1)] ?? scenes[0];
  const info = STORES[scene.mode];

  useEffect(() => {
    if (fixed || paused || list.length < 2) return;
    const t = setInterval(() => setIdx((i) => (i + 1) % list.length), 5200);
    return () => clearInterval(t);
  }, [fixed, paused, list.length]);

  return (
    <section
      data-store={scene.mode}
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      className="relative mx-4 overflow-hidden rounded-[28px] text-white shadow-[var(--shadow-2)] sm:mx-0"
    >
      {/* pre-painted tint layers cross-faded with opacity */}
      {list.map((s) => (
        <div
          key={s.mode}
          data-store={s.mode}
          aria-hidden
          className="absolute inset-0 transition-opacity duration-700"
          style={{
            opacity: s.mode === scene.mode ? 1 : 0,
            background: "radial-gradient(120% 120% at 100% 0%, var(--hero-b), var(--hero-a) 55%, color-mix(in srgb, var(--hero-a) 70%, black))",
          }}
        />
      ))}
      <div aria-hidden className="absolute -right-10 -top-16 h-64 w-64 rounded-full bg-white/10" />
      <div aria-hidden className="absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-white/5" />
      <span
        aria-hidden
        className="pointer-events-none absolute -bottom-6 right-2 select-none text-[22vw] font-black leading-none text-white/[0.07] sm:text-[140px]"
      >
        {info.short.toUpperCase()}
      </span>

      <div className="relative grid gap-6 p-5 sm:p-8 md:grid-cols-[1.15fr_1fr] md:items-center lg:p-10">
        <div key={scene.mode} className="fade-in">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur">
            <Icon name={info.icon} size={14} stroke={2} />
            {scene.mode === "all" ? "Skydrop Retail" : `${info.name} · sourced from ${info.source}`}
          </span>
          <h1 className="mt-3 text-[26px] font-extrabold leading-[1.1] tracking-tight sm:text-4xl lg:text-5xl">{info.heroTitle}</h1>
          <p className="mt-3 max-w-md text-sm text-white/85 sm:text-base">{info.heroText}</p>
          <div className="mt-5 flex flex-wrap gap-2">
            <Link href={scene.mode === "all" ? "/search?q=&store=all" : `/store/${scene.mode}`} className="btn bg-white text-[color:var(--hero-a)] shadow-lg">
              {scene.mode === "all" ? "Start shopping" : `Shop ${info.name}`} <Icon name="arrow" size={18} className="arrow" />
            </Link>
            <Link href="/how-it-works" className="btn border border-white/40 text-white hover:bg-white/10">
              How it works
            </Link>
          </div>
          <div className="mt-5 flex gap-2 md:hidden" aria-hidden>
            {scene.images.slice(0, 3).map((src, i) =>
              src ? (
                <span key={i} className="float grid h-16 w-16 place-items-center rounded-2xl bg-white p-1.5 shadow-lg" style={{ animationDelay: `${i * 0.6}s` }}>
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={src} alt="" className="h-full w-full object-contain" />
                </span>
              ) : null,
            )}
          </div>
          <div className="mt-5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/85">
            <span className="inline-flex items-center gap-1">
              <Icon name="truck" size={14} /> 7-12 day door delivery
            </span>
            <span className="inline-flex items-center gap-1">
              <Icon name="shield" size={14} /> Price verified at checkout
            </span>
          </div>
        </div>

        <div key={scene.mode + "-art"} className="fade-in relative hidden h-64 md:block lg:h-72">
          {scene.images.slice(0, 3).map((src, i) => (
            <div
              key={i}
              className="float absolute grid place-items-center overflow-hidden rounded-3xl bg-white p-3 shadow-2xl"
              style={{
                width: i === 0 ? "52%" : "36%",
                aspectRatio: "1",
                left: ["24%", "0%", "62%"][i],
                top: ["4%", "40%", "46%"][i],
                animationDelay: `${i * 0.8}s`,
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
        <div role="tablist" aria-label="Featured stores" className="relative flex gap-2 overflow-x-auto px-5 pb-5 no-scrollbar sm:px-8">
          {list.map((s, i) => (
            <button
              key={s.mode}
              role="tab"
              aria-selected={s.mode === scene.mode}
              onClick={() => setIdx(i)}
              className={
                "flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition " +
                (s.mode === scene.mode ? "bg-white text-[color:var(--hero-a)]" : "bg-white/15 text-white hover:bg-white/25")
              }
            >
              <Icon name={STORES[s.mode].icon} size={14} stroke={2} />
              {STORES[s.mode].name}
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
