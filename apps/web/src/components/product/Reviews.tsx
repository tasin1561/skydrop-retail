"use client";

import { useEffect, useRef, useState } from "react";
import type { Review } from "@/lib/types";
import { Icon } from "@/components/Icon";
import { Stars } from "@/components/ProductBits";
import { useT } from "@/i18n/client";

// Star breakdown bars fill (scaleX) when they scroll into view.
export function StarBars({ rows }: { rows: { rating: number; percentage: number }[] }) {
  const ref = useRef<HTMLDivElement>(null);
  const { t, digits } = useT();
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting) {
          setInView(true);
          io.disconnect();
        }
      },
      { threshold: 0.3 },
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);
  const sorted = [...rows].sort((a, b) => b.rating - a.rating);
  return (
    <div ref={ref} data-in={inView} className="flex flex-col gap-1.5">
      {sorted.map((r, i) => (
        <div key={r.rating} className="flex items-center gap-2.5 text-xs" aria-label={t("rv.starsAria", { rating: r.rating, pct: r.percentage })}>
          <span className="inline-flex w-8 shrink-0 items-center gap-0.5 font-semibold text-ink-2">
            {digits(r.rating)}
            <Icon name="star" size={11} fill="currentColor" stroke={1} className="text-amber-500" />
          </span>
          <span className="relative h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2">
            <span
              className="pd-bar-fill absolute inset-0 rounded-full bg-gradient-to-r from-amber-400 to-amber-500"
              style={{ ["--p" as string]: r.percentage / 100, ["--i" as string]: i }}
            />
          </span>
          <span className="w-9 shrink-0 text-right font-semibold tabular-nums text-ink-2">{digits(r.percentage)}%</span>
        </div>
      ))}
    </div>
  );
}

export function ReviewList({ reviews, source }: { reviews: Review[]; source: string }) {
  const [all, setAll] = useState(false);
  const { t } = useT();
  const shown = all ? reviews : reviews.slice(0, 4);
  return (
    <div className="flex flex-col gap-3">
      {shown.map((r, i) => (
        <ReviewCard key={i} r={r} source={source} />
      ))}
      {reviews.length > shown.length && (
        <button type="button" onClick={() => setAll(true)} className="btn btn-ghost self-center text-sm">
          {t("rv.showMore", { count: reviews.length - shown.length })} <Icon name="chevron" size={16} />
        </button>
      )}
    </div>
  );
}

function ReviewCard({ r, source }: { r: Review; source: string }) {
  const [open, setOpen] = useState(false);
  const { t } = useT();
  const long = r.content.length > 260;
  const initials = r.author
    .split(/\s+/)
    .map((w) => w[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
  const date = r.date.replace(/^Reviewed in India on\s*/i, "");
  return (
    <article className="rise rounded-2xl bg-surface p-4 shadow-[var(--shadow-1)]">
      <header className="flex items-center gap-3">
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-[14px] bg-[var(--accent-soft)] text-sm font-bold text-accent">{initials || "?"}</span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold">{r.author}</p>
          <p className="text-[11px] text-ink-3">
            {date} · {t("rv.onSource", { source })}
          </p>
        </div>
        <Stars rating={r.rating} size={12} />
      </header>
      {r.title && <h3 className="mt-3 text-sm font-bold">{r.title}</h3>}
      <p className={"mt-1 whitespace-pre-line text-sm leading-relaxed text-ink-2 " + (long && !open ? "line-clamp-4" : "")}>{r.content}</p>
      {long && (
        <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={open} className="mt-1 min-h-[44px] text-xs font-bold text-accent">
          {open ? t("rev.less") : t("rev.more")}
        </button>
      )}
    </article>
  );
}
