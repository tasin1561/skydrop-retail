"use client";

import { useLayoutEffect, useRef } from "react";
import { reducedMotion } from "./flyToCart";

const bdt = (n: number) => "৳" + Math.round(n).toLocaleString("en-IN");

/**
 * Number that counts from its previous value to the new one (about 600ms, ease-out) and ticks at the end.
 * The server renders the final text; React owns the text node and we only tween its nodeValue, so the
 * end state is always exactly what React rendered. Reduced motion: instant.
 */
export function CountUp({ value, className, format = "bdt" }: { value: number; className?: string; format?: "bdt" | "int" }) {
  const ref = useRef<HTMLSpanElement>(null);
  const prev = useRef(value);
  const fmt = format === "bdt" ? bdt : (n: number) => Math.round(n).toLocaleString("en-IN");

  useLayoutEffect(() => {
    const el = ref.current;
    const from = prev.current;
    prev.current = value;
    const node = el?.firstChild;
    if (!el || !node || from === value || reducedMotion()) return;
    let raf = 0;
    const t0 = performance.now();
    const dur = Math.min(900, 420 + Math.log10(Math.abs(value - from) + 1) * 90);
    const step = (t: number) => {
      const k = Math.min(1, (t - t0) / dur);
      const e = 1 - Math.pow(1 - k, 3);
      node.nodeValue = fmt(from + (value - from) * e);
      if (k < 1) raf = requestAnimationFrame(step);
      else {
        node.nodeValue = fmt(value);
        el.classList.remove("mo-tick");
        void el.offsetWidth;
        el.classList.add("mo-tick");
      }
    };
    node.nodeValue = fmt(from);
    raf = requestAnimationFrame(step);
    return () => {
      cancelAnimationFrame(raf);
      node.nodeValue = fmt(value);
    };
    // fmt is derived from format only
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  return (
    <span ref={ref} className={"inline-block tabular-nums " + (className ?? "")}>
      {fmt(value)}
    </span>
  );
}
