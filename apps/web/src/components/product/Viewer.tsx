"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import type { Media } from "./types";
import { VideoPlayer } from "./VideoPlayer";
import { sized } from "./media";

type P = { title: string; placeholder?: { hue: number; icon: string }; image: string | null };

// Fullscreen media viewer: a sheet with swipeable slides, tap-to-zoom, focus trap and Escape to close.
export function Viewer({
  p,
  market,
  media,
  start,
  source,
  autoPlay,
  onClose,
  onIndex,
}: {
  p: P;
  market: string;
  media: Media[];
  start: number;
  source: string;
  autoPlay: boolean;
  onClose: () => void;
  onIndex: (i: number) => void;
}) {
  const [idx, setIdx] = useState(start);
  const [closing, setClosing] = useState(false);
  const [zoom, setZoom] = useState<{ on: boolean; x: number; y: number }>({ on: false, x: 50, y: 50 });
  const railRef = useRef<HTMLDivElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const count = Math.max(1, media.length);

  const close = useCallback(() => {
    setClosing(true);
    setTimeout(onClose, 200);
  }, [onClose]);

  const go = useCallback(
    (i: number) => {
      const r = railRef.current;
      const n = Math.max(0, Math.min(count - 1, i));
      if (r) r.scrollTo({ left: n * r.clientWidth, behavior: "smooth" });
    },
    [count],
  );

  // Jump to the starting slide without animation, lock page scroll, focus close.
  useEffect(() => {
    const r = railRef.current;
    if (r) r.scrollLeft = start * r.clientWidth;
    const prev = document.activeElement as HTMLElement | null;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeRef.current?.focus();
    return () => {
      document.body.style.overflow = overflow;
      prev?.focus?.();
    };
  }, [start]);

  // Keyboard: Escape closes, arrows move, Tab is trapped inside the dialog.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") {
        e.preventDefault();
        close();
      } else if (e.key === "ArrowRight") go(idx + 1);
      else if (e.key === "ArrowLeft") go(idx - 1);
      else if (e.key === "Tab" && dialogRef.current) {
        const f = Array.from(
          dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled]), [href], video[controls], [tabindex]:not([tabindex="-1"])'),
        ).filter((el) => el.offsetParent !== null);
        if (!f.length) return;
        const first = f[0];
        const last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [idx, go, close]);

  function onScroll() {
    const r = railRef.current;
    if (!r) return;
    const i = Math.round(r.scrollLeft / r.clientWidth);
    if (i !== idx) {
      setIdx(i);
      onIndex(i);
      setZoom({ on: false, x: 50, y: 50 });
    }
  }

  function originFrom(e: React.MouseEvent<HTMLElement>) {
    const b = e.currentTarget.getBoundingClientRect();
    return { x: ((e.clientX - b.left) / b.width) * 100, y: ((e.clientY - b.top) / b.height) * 100 };
  }

  const current = media[idx];

  return createPortal(
    <div
      ref={dialogRef}
      className="pd-viewer fixed inset-0 z-[80]"
      data-closing={closing}
      data-store={market}
      role="dialog"
      aria-modal="true"
      aria-label={`Photos and videos: ${p.title}`}
    >
      <div className="pd-backdrop absolute inset-0 bg-[#060a14]/80 backdrop-blur-md" onClick={close} />
      <div className="pd-panel sheet-enter absolute inset-x-0 bottom-0 top-0 flex flex-col bg-surface md:inset-6 md:rounded-[20px] md:shadow-[var(--shadow-2)]">
        <div className="flex items-center gap-3 px-3 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))]">
          <p className="min-w-0 flex-1 truncate pl-1 text-sm font-semibold text-ink-2">{p.title}</p>
          <span className="rounded-full bg-surface-2 px-3 py-1 text-xs font-bold tabular-nums text-ink-2" aria-live="polite">
            {idx + 1} / {count}
          </span>
          <button
            ref={closeRef}
            type="button"
            onClick={close}
            aria-label="Close viewer"
            className="grid h-11 w-11 place-items-center rounded-full bg-surface-2 text-ink transition hover:bg-[var(--accent-soft)] hover:text-accent active:scale-95"
          >
            <Icon name="x" size={20} />
          </button>
        </div>

        <div className="relative min-h-0 flex-1">
          <div
            ref={railRef}
            onScroll={onScroll}
            className={"no-scrollbar snap-x-rail flex h-full " + (zoom.on ? "overflow-hidden" : "overflow-x-auto")}
          >
            {media.length === 0 && (
              <div className="grid h-full w-full shrink-0 place-items-center p-6">
                <ProductImage p={p} sizes="lg" className="aspect-square w-full max-w-md rounded-3xl" />
              </div>
            )}
            {media.map((m, i) => (
              <div key={i} className="grid h-full w-full shrink-0 place-items-center overflow-hidden p-3 md:p-6" aria-roledescription="slide" aria-label={`${i + 1} of ${count}`}>
                {m.kind === "image" ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={m.src}
                    alt={`${p.title}, image ${i + 1}`}
                    loading={Math.abs(i - start) <= 1 ? "eager" : "lazy"}
                    decoding="async"
                    draggable={false}
                    data-zoom={i === idx && zoom.on}
                    onClick={(e) => {
                      const o = originFrom(e);
                      setZoom((z) => (z.on ? { on: false, x: 50, y: 50 } : { on: true, ...o }));
                    }}
                    onPointerMove={(e) => {
                      if (i === idx && zoom.on) setZoom({ on: true, ...originFrom(e) });
                    }}
                    style={{
                      transform: i === idx && zoom.on ? "scale(2.2)" : "scale(1)",
                      transformOrigin: `${zoom.x}% ${zoom.y}%`,
                      touchAction: i === idx && zoom.on ? "none" : "pan-x pan-y",
                    }}
                    className="pd-zoomable max-h-full max-w-full select-none rounded-2xl bg-white object-contain"
                  />
                ) : (
                  <VideoPlayer hls={m.hls} thumb={m.thumb} active={i === idx} autoPlay={autoPlay && i === start} source={source} title={p.title} />
                )}
              </div>
            ))}
          </div>

          {count > 1 && (
            <>
              <button
                type="button"
                onClick={() => go(idx - 1)}
                disabled={idx === 0}
                aria-label="Previous"
                className="absolute left-3 top-1/2 hidden h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-surface text-ink shadow-[var(--shadow-2)] transition hover:scale-105 hover:text-accent active:scale-95 disabled:opacity-0 md:grid"
              >
                <Icon name="left" size={22} />
              </button>
              <button
                type="button"
                onClick={() => go(idx + 1)}
                disabled={idx === count - 1}
                aria-label="Next"
                className="absolute right-3 top-1/2 hidden h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-surface text-ink shadow-[var(--shadow-2)] transition hover:scale-105 hover:text-accent active:scale-95 disabled:opacity-0 md:grid"
              >
                <Icon name="right" size={22} />
              </button>
            </>
          )}
        </div>

        <div className={"flex items-center justify-center gap-2 px-3 pt-2 " + (count > 1 ? "pb-2" : "pb-[max(0.75rem,env(safe-area-inset-bottom))]")}>
          <p className="hidden text-xs text-ink-3 sm:block">
            {current?.kind === "video" ? "Seller video" : zoom.on ? "Tap again to zoom out" : "Tap image to zoom"} · from {source}
          </p>
        </div>
        {count > 1 && (
          <div className="no-scrollbar flex justify-start gap-2 overflow-x-auto px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:justify-center">
            {media.map((m, i) => {
              const src = m.kind === "image" ? sized(m.src, "SS120") : sized(m.thumb, "SS120");
              return (
                <button
                  key={i}
                  type="button"
                  onClick={() => go(i)}
                  aria-label={`Show ${m.kind === "video" ? "video" : "image"} ${i + 1}`}
                  aria-current={i === idx}
                  className={
                    "relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-white transition duration-300 active:scale-95 " +
                    (i === idx ? "opacity-100 shadow-[0_0_0_2px_var(--accent)]" : "opacity-55 hover:opacity-90")
                  }
                >
                  {src ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={src} alt="" loading="lazy" className="h-full w-full object-contain" />
                  ) : (
                    <span className="grid h-full w-full place-items-center bg-surface-2 text-ink-2">
                      <Icon name="play" size={16} />
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
      </div>
    </div>,
    document.body,
  );
}
