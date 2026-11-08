"use client";

import { useEffect, useRef } from "react";
import { isMarket, storeText } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { useT } from "@/i18n/client";
import "./storename.css";

// One IntersectionObserver for every store name on the page: the shine sweeps once when a name first
// comes into view, then the observer lets go of it. Hover / focus / selection replays are pure CSS.
let io: IntersectionObserver | null = null;
function watch(el: HTMLElement) {
  if (typeof IntersectionObserver === "undefined") return () => {};
  if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return () => {};
  io ??= new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (!e.isIntersecting) continue;
        const t = e.target as HTMLElement;
        io?.unobserve(t);
        t.dataset.seen = "1";
        t.addEventListener("animationend", () => delete t.dataset.seen, { once: true });
      }
    },
    { threshold: 0.6 },
  );
  io.observe(el);
  return () => io?.unobserve(el);
}

export type StoreNameProps = {
  mode: StoreMode;
  /** plain = inline text, pill = text on a soft accent tint with a 1px ring, hero = large display name */
  variant?: "plain" | "pill" | "hero";
  /** override the label (e.g. a short name); defaults to the store's localised name */
  name?: string;
  /** show the muted "· amazon.in" source after the name (markets only); pass a string to override */
  source?: boolean | string;
  /** selected / sitting on the store's filled accent: white text; a pill paints the fill itself */
  fill?: boolean;
  /** pill without its own background (a parent paints the fill, e.g. the header's sliding indicator) */
  bare?: boolean;
  /** force the light or dark token set (for names on a fixed white or deep-tint surface) */
  tone?: "light" | "dark";
  /** Tailwind text-size classes replacing the variant's default size */
  size?: string;
  className?: string;
};

/** A store's name as text in its brand colours: the only way stores are identified (no logos or icons). */
export function StoreName({ mode, variant = "plain", name, source, fill, bare, tone, size, className = "" }: StoreNameProps) {
  const { t } = useT();
  const ref = useRef<HTMLSpanElement>(null);
  useEffect(() => (ref.current ? watch(ref.current) : undefined), []);
  const s = storeText(mode, t);
  const label = name ?? s.name;
  const src = typeof source === "string" ? source : source && isMarket(mode) ? s.source : null;
  const muted = fill ? "opacity-80" : tone === "dark" ? "text-white/75" : "text-ink-3";
  const weight = variant === "hero" ? "font-semibold leading-[1.15] tracking-[-0.01em]" : "font-semibold";
  const fontSize = size ?? (variant === "hero" ? "text-2xl sm:text-[28px]" : variant === "pill" ? "text-sm" : "");
  return (
    <span
      ref={ref}
      data-store={mode}
      data-tone={tone}
      data-fill={fill ? "" : undefined}
      data-bare={bare ? "" : undefined}
      className={`sn ${variant === "pill" ? "sn-pill" : variant === "hero" ? "block" : "inline"} ${variant === "pill" ? "min-h-7" : ""} ${weight} ${fontSize} ${className}`}
    >
      <span className="sn-g">{label}</span>
      {src &&
        (variant === "hero" ? (
          <span className={"mt-1 block text-sm font-normal tracking-normal " + muted}>{t("home.sourcedFrom", { source: src })}</span>
        ) : (
          <span className={(variant === "pill" ? "" : "ml-1.5 ") + "whitespace-nowrap text-[0.8em] font-medium " + muted}>
            <span aria-hidden>· </span>
            {src}
          </span>
        ))}
    </span>
  );
}
