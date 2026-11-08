"use client";

import "./cms.css";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import type { Banner } from "@/lib/cms";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

const AUTOPLAY_MS = 8000; // calm: ≥ 7s, pauses on hover/focus, off-screen, hidden tab and reduced motion

// Banner carousel: CSS scroll-snap does the swiping; autoplay pauses when off-screen, when the tab is hidden,
// on hover/focus, after the shopper swipes, and entirely under reduced motion. The dot bead tracks the scroll.
export function HeroCarousel({ banners, label: labelProp }: { banners: Banner[]; label?: string }) {
  const { t } = useT();
  const label = labelProp ?? t("hc.featured");
  const track = useRef<HTMLDivElement>(null);
  const dots = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [visible, setVisible] = useState(false);
  const [held, setHeld] = useState(false); // hover / focus
  const [calm, setCalm] = useState(true); // prefers-reduced-motion (assume calm until mounted)
  const [pageHidden, setPageHidden] = useState(false);
  const [userPause, setUserPause] = useState(0); // bumps when the shopper swipes; restarts the timer
  const n = banners.length;
  const playing = n > 1 && visible && !held && !calm && !pageHidden;

  const go = useCallback((i: number, smooth = true) => {
    const el = track.current;
    if (!el) return;
    const next = ((i % n) + n) % n;
    el.scrollTo({ left: next * el.clientWidth, behavior: smooth ? "smooth" : "auto" });
  }, [n]);

  // scroll position → active slide + bead position (no re-render per frame for the bead)
  useEffect(() => {
    const el = track.current;
    if (!el) return;
    let raf = 0;
    const onScroll = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const pos = el.scrollLeft / Math.max(1, el.clientWidth);
        dots.current?.style.setProperty("--hc-pos", String(Math.min(n - 1, Math.max(0, pos))));
        setActive(Math.round(pos));
      });
    };
    onScroll();
    el.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      cancelAnimationFrame(raf);
      el.removeEventListener("scroll", onScroll);
    };
  }, [n]);

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const io = new IntersectionObserver(([e]) => setVisible(e.isIntersecting && e.intersectionRatio > 0.4), { threshold: [0, 0.4, 0.8] });
    io.observe(el);
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const onMq = () => setCalm(mq.matches);
    onMq();
    mq.addEventListener("change", onMq);
    const onVis = () => setPageHidden(document.hidden);
    document.addEventListener("visibilitychange", onVis);
    return () => {
      io.disconnect();
      mq.removeEventListener("change", onMq);
      document.removeEventListener("visibilitychange", onVis);
    };
  }, []);

  useEffect(() => {
    if (!playing) return;
    const t = setTimeout(() => go(active + 1), AUTOPLAY_MS);
    return () => clearTimeout(t);
  }, [playing, active, go, userPause]);

  if (!n) return null;

  return (
    <section
      aria-roledescription="carousel"
      aria-label={label}
      className="relative mt-4 px-4 sm:px-0"
      onMouseEnter={() => setHeld(true)}
      onMouseLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={(e) => !e.currentTarget.contains(e.relatedTarget as Node) && setHeld(false)}
    >
      <div
        ref={track}
        className="hc-track"
        onPointerDown={() => setUserPause((x) => x + 1)}
        onKeyDown={(e) => {
          if (e.key === "ArrowRight") go(active + 1);
          if (e.key === "ArrowLeft") go(active - 1);
        }}
      >
        {banners.map((b, i) => (
          <Slide key={b.id} b={b} i={i} n={n} active={i === active} />
        ))}
      </div>

      {n > 1 && (
        <>
          <button
            type="button"
            onClick={() => go(active - 1)}
            aria-label={t("hc.prev")}
            className="absolute left-6 top-[calc(50%-22px)] hidden h-11 w-11 place-items-center rounded-full bg-surface/85 text-ink shadow-[var(--shadow-2)] backdrop-blur transition-transform hover:bg-surface sm:-left-3 sm:grid"
          >
            <Icon name="left" size={18} />
          </button>
          <button
            type="button"
            onClick={() => go(active + 1)}
            aria-label={t("hc.next")}
            className="absolute right-6 top-[calc(50%-22px)] hidden h-11 w-11 place-items-center rounded-full bg-surface/85 text-ink shadow-[var(--shadow-2)] backdrop-blur transition-transform hover:bg-surface sm:-right-3 sm:grid"
          >
            <Icon name="right" size={18} />
          </button>
          <div className="mt-1 flex items-center justify-center gap-3">
            <div ref={dots} className="hc-dots" role="group" aria-label={t("hc.choose")}>
              {banners.map((b, i) => (
                <button
                  key={b.id}
                  type="button"
                  className="hc-dot"
                  aria-label={t("hc.show", { i: i + 1, n, title: b.title })}
                  aria-current={i === active ? "true" : undefined}
                  onClick={() => go(i)}
                >
                  <span />
                </button>
              ))}
              <span className="hc-bead" aria-hidden />
            </div>
            {!calm && (
              <span className="relative h-1 w-10 overflow-hidden rounded-full bg-surface-2" aria-hidden>
                <span
                  key={`${active}-${userPause}-${playing}`}
                  data-run={playing}
                  className="hc-progress absolute inset-0 rounded-full bg-accent"
                  style={{ ["--hc-ms" as string]: `${AUTOPLAY_MS}ms` }}
                />
              </span>
            )}
          </div>
        </>
      )}
    </section>
  );
}

function Slide({ b, i, n, active }: { b: Banner; i: number; n: number; active: boolean }) {
  const cover = !!b.image && b.imageFit !== "contain";
  const { t } = useT();
  const body = (
    <>
      {cover ? (
        <>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={b.image!} alt="" className="hc-cover" loading={i === 0 ? "eager" : "lazy"} decoding="async" />
          <span className="hc-scrim" aria-hidden />
        </>
      ) : (
        <>
          <span className="hc-blob -right-10 -top-16 h-56 w-56" aria-hidden />
          <span className="hc-blob -bottom-20 left-1/3 h-40 w-40" style={{ animationDelay: "-5s" }} aria-hidden />
        </>
      )}
      <div className="flex h-full min-h-[inherit] items-center gap-4 p-5 sm:p-8">
        <div className="hc-copy min-w-0 flex-1">
          <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-white/75" style={{ ["--d" as string]: 0 }}>
            {i + 1} / {n}
          </p>
          <h2 className="mt-1 text-xl font-extrabold leading-tight tracking-tight [text-wrap:balance] sm:text-3xl" style={{ ["--d" as string]: 1 }}>
            {b.title}
          </h2>
          {b.subtitle && (
            <p className="mt-1.5 line-clamp-2 max-w-xl text-sm text-white/85 sm:text-base" style={{ ["--d" as string]: 2 }}>
              {b.subtitle}
            </p>
          )}
          {b.link && (
            <span
              className="mt-4 inline-flex min-h-11 items-center gap-1.5 rounded-full bg-white px-4 text-sm font-bold text-[#0b0b0f] shadow-[var(--glow)]"
              style={{ ["--d" as string]: 3 }}
            >
              {b.cta || t("cat.shopNow")} <Icon name="arrow" size={16} />
            </span>
          )}
        </div>
        {b.image && !cover && (
          <div className="hc-shot hidden h-36 w-36 shrink-0 place-items-center rounded-[16px] img-well bg-surface-2 p-3 min-[420px]:grid sm:h-48 sm:w-48">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={b.image} alt="" className="h-full w-full object-contain" loading="lazy" decoding="async" />
          </div>
        )}
      </div>
    </>
  );
  const common = {
    className: "hc-slide block",
    "data-store": b.store,
    "data-active": active,
    role: "group",
    "aria-roledescription": "slide",
    "aria-label": t("hc.slide", { i: i + 1, n }),
  } as const;
  if (!b.link) return <div {...common}>{body}</div>;
  const external = /^https?:\/\//.test(b.link);
  return external ? (
    <a {...common} href={b.link} rel="noopener" tabIndex={active ? 0 : -1}>
      {body}
    </a>
  ) : (
    <Link {...common} href={b.link} prefetch={false} tabIndex={active ? 0 : -1}>
      {body}
    </Link>
  );
}

/** Promo-strip and category-tile banners (placement "strip" / "category"). */
export function BannerTiles({ banners }: { banners: Banner[] }) {
  if (!banners.length) return null;
  return (
    <section className="no-scrollbar mt-6 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 sm:grid sm:grid-cols-2 sm:px-0 lg:grid-cols-3">
      {banners.map((b, i) => {
        const inner = (
          <div
            data-store={b.store}
            className="rise relative flex h-full min-h-24 items-center gap-3 overflow-hidden rounded-2xl p-4 text-white"
            style={{ ["--i" as string]: i, background: "linear-gradient(120deg, var(--hero-a), var(--hero-b))" }}
          >
            {b.image && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={b.image} alt="" loading="lazy" className="absolute inset-0 -z-0 h-full w-full object-cover opacity-35" />
            )}
            <div className="relative min-w-0 flex-1">
              <p className="font-extrabold leading-tight">{b.title}</p>
              {b.subtitle && <p className="mt-0.5 line-clamp-2 text-xs text-white/85">{b.subtitle}</p>}
            </div>
            {b.link && <Icon name="arrow" size={18} className="relative shrink-0" />}
          </div>
        );
        return (
          <div key={b.id} className="w-[78vw] max-w-[340px] shrink-0 snap-start sm:w-auto sm:max-w-none">
            {b.link ? (
              <Link href={b.link} prefetch={false} className="block h-full rounded-2xl transition-transform hover:-translate-y-0.5">
                {inner}
              </Link>
            ) : (
              inner
            )}
          </div>
        );
      })}
    </section>
  );
}
