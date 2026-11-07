"use client";

import { useLayoutEffect, useRef, useState } from "react";
import type { Variant } from "@/lib/types";
import { Icon } from "@/components/Icon";
import { sized } from "./media";

export type DimGroup = {
  key: string;
  values: { value: string; swatch: string | null }[];
};

export function groupVariants(variants: Variant[]): DimGroup[] {
  const map = new Map<string, Map<string, string | null>>();
  for (const v of variants) {
    for (const [k, val] of Object.entries(v.dimensions)) {
      if (!map.has(k)) map.set(k, new Map());
      const m = map.get(k)!;
      if (!m.has(val) || (!m.get(val) && v.swatch)) m.set(val, v.swatch);
    }
  }
  const groups: DimGroup[] = [];
  map.forEach((vals, key) => {
    const list = Array.from(vals, ([value, swatch]) => ({ value, swatch }));
    // Sizes read best in numeric order (6 UK, 7 UK ... / S, M, L).
    if (!/colou?r|shade|style|pattern/i.test(key)) list.sort((a, b) => sizeRank(a.value) - sizeRank(b.value));
    groups.push({ key, values: list });
  });
  // Swatch-based (colour) groups first.
  return groups.sort((a, b) => Number(b.values.some((v) => v.swatch)) - Number(a.values.some((v) => v.swatch)));
}

const LETTER = ["XXS", "XS", "S", "M", "L", "XL", "XXL", "XXXL", "3XL", "4XL"];
function sizeRank(v: string) {
  const l = LETTER.indexOf(v.toUpperCase().trim());
  if (l >= 0) return l;
  const n = parseFloat(v.replace(/[^\d.]/g, ""));
  return Number.isFinite(n) ? 100 + n : 10000;
}

// Swatches (when images exist) or pill chips, with an accent indicator that travels to the selection.
export function VariantGroup({
  group,
  selected,
  isAvailable,
  isUnavailable,
  onPick,
  pending,
}: {
  group: DimGroup;
  selected: string | undefined;
  isAvailable: (value: string) => boolean; // exists in combination with the other selected values
  isUnavailable: (value: string) => boolean; // known out of stock
  onPick: (value: string) => void;
  pending: string | null;
}) {
  const wrap = useRef<HTMLDivElement>(null);
  const [box, setBox] = useState<{ x: number; y: number; w: number; h: number } | null>(null);
  const [ready, setReady] = useState(false);
  const swatches = group.values.some((v) => v.swatch);
  const [expanded, setExpanded] = useState(false);
  const LIMIT = swatches ? 12 : 16;
  const selIdx = group.values.findIndex((v) => v.value === selected);
  const shown = expanded || group.values.length <= LIMIT ? group.values : group.values.slice(0, Math.max(LIMIT, selIdx + 1));

  useLayoutEffect(() => {
    const w = wrap.current;
    if (!w) return;
    const measure = () => {
      const el = w.querySelector<HTMLElement>('[aria-checked="true"]');
      if (!el) return setBox(null);
      setBox({ x: el.offsetLeft, y: el.offsetTop, w: el.offsetWidth, h: el.offsetHeight });
    };
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(w);
    const id = requestAnimationFrame(() => setReady(true));
    return () => {
      ro.disconnect();
      cancelAnimationFrame(id);
    };
  }, [selected, expanded]);

  function onKeyDown(e: React.KeyboardEvent<HTMLDivElement>) {
    const keys = ["ArrowRight", "ArrowDown", "ArrowLeft", "ArrowUp"];
    if (!keys.includes(e.key)) return;
    e.preventDefault();
    const btns = Array.from(e.currentTarget.querySelectorAll<HTMLButtonElement>('[role="radio"]:not([aria-disabled="true"])'));
    const at = btns.indexOf(document.activeElement as HTMLButtonElement);
    const step = e.key === "ArrowRight" || e.key === "ArrowDown" ? 1 : -1;
    btns[(at + step + btns.length) % btns.length]?.focus();
  }

  return (
    <div>
      <p className="mb-2 flex flex-wrap items-baseline gap-x-1.5 text-sm" id={`vg-${group.key}`}>
        <span className="font-semibold text-ink-2">{group.key}:</span>
        <span className="font-bold text-ink" aria-live="polite">
          {selected ?? "Choose one"}
        </span>
        {pending && (
          <span className="inline-flex items-center gap-1 text-xs font-semibold text-accent">
            <Spinner /> Opening {pending}
          </span>
        )}
      </p>
      <div
        ref={wrap}
        role="radiogroup"
        aria-labelledby={`vg-${group.key}`}
        onKeyDown={onKeyDown}
        className={"relative flex flex-wrap " + (swatches ? "gap-2.5" : "gap-2")}
      >
        {box && (
          <span
            aria-hidden
            data-ready={ready}
            className={"pd-var-ind" + (swatches ? " is-swatch" : "")}
            style={{ width: box.w, height: box.h, transform: `translate(${box.x}px, ${box.y}px)` }}
          />
        )}
        {shown.map((v) => {
          const on = v.value === selected;
          const gone = isUnavailable(v.value);
          const combo = isAvailable(v.value);
          const label = `${v.value}${gone ? ", unavailable" : !combo ? ", other options change" : ""}`;
          return (
            <button
              key={v.value}
              type="button"
              role="radio"
              aria-checked={on}
              aria-disabled={gone}
              aria-label={label}
              title={gone ? `${v.value} (unavailable)` : v.value}
              tabIndex={on || (selected === undefined && v === shown[0]) ? 0 : -1}
              onClick={() => {
                if (!gone && !on) onPick(v.value);
              }}
              className={
                "group relative z-[1] transition duration-200 " +
                (gone ? "cursor-not-allowed opacity-45 " : "hover:-translate-y-0.5 active:scale-95 ") +
                (swatches
                  ? "flex w-[68px] flex-col items-center gap-1 rounded-[14px] p-1.5 "
                  : "min-h-[44px] min-w-[52px] rounded-[14px] px-3.5 text-sm font-semibold " +
                    (on ? "text-accent " : "bg-surface-2 text-ink hover:bg-[var(--accent-soft)] hover:text-accent ") +
                    (!combo && !gone && !on ? "text-ink-3 " : ""))
              }
            >
              {swatches ? (
                <>
                  <span className="relative grid h-14 w-14 place-items-center overflow-hidden rounded-[11px] bg-white shadow-[var(--shadow-1)]">
                    {v.swatch ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={sized(v.swatch, "SS112") ?? v.swatch} alt="" loading="lazy" decoding="async" className="h-full w-full object-cover" />
                    ) : (
                      <span className="text-xs font-bold text-ink-2">{v.value.slice(0, 2)}</span>
                    )}
                    {gone && (
                      <span className="absolute inset-0 grid place-items-center bg-surface/60">
                        <span className="h-[2px] w-[130%] -rotate-45 bg-ink-3" />
                      </span>
                    )}
                    {on && (
                      <span className="pop absolute right-0.5 top-0.5 grid h-4 w-4 place-items-center rounded-full bg-accent text-white shadow">
                        <Icon name="check" size={10} stroke={3} />
                      </span>
                    )}
                  </span>
                  <span className={"line-clamp-2 w-full text-center text-[10.5px] leading-tight " + (on ? "font-bold text-accent" : "text-ink-2")}>
                    {v.value}
                  </span>
                  {gone && <span className="-mt-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-bad">Unavailable</span>}
                </>
              ) : (
                <span className="flex flex-col items-center leading-tight">
                  <span className={gone ? "line-through" : ""}>{v.value}</span>
                  {gone && <span className="text-[9.5px] font-semibold uppercase tracking-wide text-bad">Unavailable</span>}
                </span>
              )}
            </button>
          );
        })}
        {group.values.length > shown.length && (
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="min-h-[44px] rounded-[14px] px-3 text-sm font-semibold text-accent transition hover:bg-[var(--accent-soft)]"
          >
            +{group.values.length - shown.length} more
          </button>
        )}
      </div>
    </div>
  );
}

export function Spinner({ size = 14 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" className="pd-spin" aria-hidden>
      <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeOpacity=".25" strokeWidth="3" />
      <path d="M21 12a9 9 0 0 0-9-9" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  );
}
