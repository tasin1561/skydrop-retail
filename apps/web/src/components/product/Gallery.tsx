"use client";

import { useRef, useState } from "react";
import dynamic from "next/dynamic";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import type { Media } from "./types";
import { sized } from "./media";
import { flipOpen } from "@/components/motion/flipOpen";

// Viewer (and with it the video player) is only loaded once someone opens it.
const Viewer = dynamic(() => import("./Viewer").then((m) => m.Viewer), { ssr: false });

type P = { title: string; placeholder?: { hue: number; icon: string }; image: string | null };

export function Gallery({
  p,
  market,
  media,
  source,
  hasVideosOnly,
  badges,
}: {
  p: P;
  market: string;
  media: Media[];
  source: string;
  hasVideosOnly?: boolean;
  badges?: React.ReactNode;
}) {
  const [idx, setIdx] = useState(0);
  const [view, setView] = useState<{ start: number; autoPlay: boolean } | null>(null);
  const railRef = useRef<HTMLDivElement>(null);
  const pillRef = useRef<HTMLSpanElement>(null);
  const count = media.length;

  // Mobile: the dot pill follows the scroll position continuously (transform only, no re-render per frame).
  function onScroll() {
    const r = railRef.current;
    if (!r) return;
    const pos = r.scrollLeft / Math.max(1, r.clientWidth);
    if (pillRef.current) pillRef.current.style.transform = `translateX(${pos * 12}px)`;
    const i = Math.round(pos);
    if (i !== idx) setIdx(i);
  }

  function select(i: number) {
    setIdx(i);
    const r = railRef.current;
    if (r) r.scrollTo({ left: i * r.clientWidth, behavior: "smooth" });
  }

  function open(i: number, autoPlay = false, from?: Element | null) {
    const m = media[i];
    if (!autoPlay && m?.kind === "image") flipOpen(from?.querySelector("img") ?? from, m.src);
    setView({ start: i, autoPlay });
  }

  const current = media[idx];

  return (
    <div className="lg:sticky lg:top-24">
      <div className="lg:flex lg:gap-4">
        {/* desktop thumbnail rail */}
        {count > 1 && (
          <div className="relative hidden max-h-[560px] w-16 shrink-0 flex-col gap-2 overflow-y-auto no-scrollbar lg:flex" role="tablist" aria-label="Product media" aria-orientation="vertical">
            <span aria-hidden className="pd-thumb-ring" style={{ transform: `translateY(${idx * 72}px)` }} />
            {media.map((m, i) => {
              const src = m.kind === "image" ? sized(m.src, "SS128") : sized(m.thumb, "SS128");
              return (
                <button
                  key={i}
                  type="button"
                  role="tab"
                  aria-selected={i === idx}
                  aria-label={m.kind === "video" ? `Video ${i + 1}` : `Image ${i + 1}`}
                  onMouseEnter={() => setIdx(i)}
                  onFocus={() => setIdx(i)}
                  onClick={() => (m.kind === "video" ? open(i, true) : setIdx(i))}
                  onKeyDown={(e) => {
                    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                      e.preventDefault();
                      const n = (i + (e.key === "ArrowDown" ? 1 : -1) + count) % count;
                      (e.currentTarget.parentElement?.querySelectorAll("button")[n] as HTMLButtonElement | undefined)?.focus();
                    }
                  }}
                  className={
                    "group relative h-16 w-16 shrink-0 overflow-hidden rounded-[14px] bg-white shadow-[var(--shadow-1)] transition duration-200 hover:-translate-y-0.5 active:scale-95 " +
                    (i === idx ? "opacity-100" : "opacity-70 hover:opacity-100")
                  }
                >
                  {src ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={src} alt="" loading="lazy" decoding="async" className="h-full w-full object-contain p-1" />
                  ) : (
                    <span className="grid h-full w-full place-items-center bg-surface-2 text-ink-2">
                      <Icon name="play" size={18} />
                    </span>
                  )}
                  {m.kind === "video" && (
                    <span className="pd-play absolute inset-0 m-auto h-7 w-7">
                      <Icon name="play" size={12} fill="currentColor" stroke={1} />
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        )}

        <div className="relative min-w-0 flex-1">
          {/* mobile: swipeable rail */}
          <div
            ref={railRef}
            onScroll={onScroll}
            className="no-scrollbar snap-x-rail flex overflow-x-auto bg-white lg:hidden"
            aria-roledescription="carousel"
            aria-label="Product photos"
          >
            {count === 0 && (
              <button type="button" onClick={() => open(0)} className="aspect-square w-full shrink-0" aria-label="Open image viewer">
                <ProductImage p={p} sizes="lg" className="h-full w-full" />
              </button>
            )}
            {media.map((m, i) => (
              <button
                key={i}
                type="button"
                onClick={(e) => open(i, m.kind === "video", e.currentTarget)}
                aria-label={m.kind === "video" ? `Play video ${i + 1} of ${count}` : `Open image ${i + 1} of ${count} fullscreen`}
                className="group relative aspect-square w-full shrink-0 p-6"
              >
                {m.kind === "image" ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={m.src}
                    alt={i === 0 ? p.title : ""}
                    loading={i === 0 ? "eager" : "lazy"}
                    fetchPriority={i === 0 ? "high" : "auto"}
                    decoding="async"
                    className="h-full w-full object-contain"
                  />
                ) : (
                  <VideoThumb thumb={m.thumb} size="lg" />
                )}
              </button>
            ))}
          </div>

          {/* desktop: main image */}
          <div className="relative hidden aspect-square overflow-hidden rounded-[20px] bg-white shadow-[var(--shadow-1)] lg:block">
            <button
              type="button"
              onClick={(e) => open(idx, current?.kind === "video", e.currentTarget)}
              className="group absolute inset-0 grid cursor-zoom-in place-items-center p-10"
              aria-label={current?.kind === "video" ? "Play video" : "Open image fullscreen"}
            >
              {!current && <ProductImage p={p} sizes="lg" className="h-full w-full rounded-2xl" />}
              {current?.kind === "image" && (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  key={current.src}
                  src={current.src}
                  alt={p.title}
                  decoding="async"
                  className="pd-fade h-full w-full object-contain transition-transform duration-500 group-hover:scale-[1.03]"
                />
              )}
              {current?.kind === "video" && <VideoThumb key={"v" + idx} thumb={current.thumb} size="lg" />}
            </button>
            {current?.kind === "image" && (
              <span className="pointer-events-none absolute bottom-4 right-4 inline-flex items-center gap-1.5 rounded-full bg-surface/90 px-3 py-1.5 text-xs font-semibold text-ink-2 shadow-[var(--shadow-1)] backdrop-blur">
                <Icon name="search" size={14} /> Click to zoom
              </span>
            )}
          </div>

          {/* overlays: badges top-left */}
          {badges && <div className="pointer-events-none absolute left-3 top-3 flex flex-col items-start gap-1.5">{badges}</div>}

          {/* mobile counters + dots */}
          {count > 1 && (
            <>
              <span className="absolute right-3 top-3 rounded-full bg-surface/90 px-2.5 py-1 text-[11px] font-bold tabular-nums text-ink-2 shadow-[var(--shadow-1)] backdrop-blur lg:hidden" aria-live="polite">
                {idx + 1}/{count}
              </span>
              <div className="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center lg:hidden">
                <div className="rounded-full bg-surface/85 px-1 py-[7px] shadow-[var(--shadow-1)] backdrop-blur">
                  <div className="pd-dots" aria-hidden>
                    {media.map((m, i) => (
                      <span key={i} className={"pd-dot" + (m.kind === "video" ? " is-video" : "")} />
                    ))}
                    <span ref={pillRef} className="pd-dot-pill" style={{ transform: "translateX(0px)" }} />
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>

      {hasVideosOnly && (
        <p className="mt-2 flex items-center gap-1.5 px-4 text-xs text-ink-2 lg:px-0">
          <Icon name="play" size={13} /> The seller has videos for this item on {source}.
        </p>
      )}

      {view && (
        <Viewer
          p={p}
          market={market}
          media={media}
          start={view.start}
          source={source}
          autoPlay={view.autoPlay}
          onIndex={(i) => select(i)}
          onClose={() => setView(null)}
        />
      )}
    </div>
  );
}

function VideoThumb({ thumb, size }: { thumb: string | null; size: "lg" | "sm" }) {
  return (
    <span className="relative grid h-full w-full place-items-center overflow-hidden rounded-2xl bg-surface-2">
      {thumb && (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={thumb} alt="" loading="lazy" decoding="async" className="absolute inset-0 h-full w-full object-cover" />
      )}
      <span className="pd-play relative h-16 w-16">
        <Icon name="play" size={size === "lg" ? 26 : 14} fill="currentColor" stroke={1} className="translate-x-0.5" />
      </span>
      <span className="absolute bottom-3 left-3 rounded-full bg-[#0b0b0f]/70 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
        Video
      </span>
    </span>
  );
}
