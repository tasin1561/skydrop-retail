"use client";

import { useEffect, useRef } from "react";
import { useStore } from "@/state/store";
import { bumpCart, isFlying } from "./flyToCart";

/**
 * One tiny runtime for the whole storefront (mounted once in the root layout):
 *  - [data-reveal]: marked data-revealed when it scrolls into view. The calm motion system draws no
 *    scroll-reveal animation any more (content is always visible); the marker is kept for hooks/tests.
 *  - [data-loop]: CSS loops (shimmers, illustrations) pause while off-screen.
 *  - cart count going up bumps every [data-cart-target] (unless a fly-to-cart parcel will land and do it).
 * Server components opt in with plain data attributes; no client wrapper needed.
 */
export function MotionRuntime() {
  const { cartCount, ready } = useStore();
  const last = useRef<number | null>(null);

  useEffect(() => {
    if (!ready) return;
    if (last.current !== null && cartCount > last.current && !isFlying()) bumpCart();
    last.current = cartCount;
  }, [cartCount, ready]);

  useEffect(() => {
    if (typeof IntersectionObserver === "undefined") return;
    const root = document.documentElement;
    const vh = () => window.innerHeight || root.clientHeight;

    const reveal = new IntersectionObserver(
      (entries) => {
        let n = 0;
        for (const e of entries) {
          if (!e.isIntersecting) continue;
          const el = e.target as HTMLElement;
          el.style.setProperty("--ri", String(n++));
          el.dataset.revealed = "true";
          reveal.unobserve(el);
        }
      },
      { rootMargin: "0px 0px -6% 0px", threshold: 0.01 },
    );
    const loops = new IntersectionObserver(
      (entries) => {
        for (const e of entries) (e.target as HTMLElement).dataset.playing = e.isIntersecting ? "true" : "false";
      },
      { rootMargin: "80px" },
    );

    const seen = new WeakSet<Element>();
    const scan = (scope: ParentNode, first: boolean) => {
      scope.querySelectorAll<HTMLElement>("[data-reveal]:not([data-revealed])").forEach((el) => {
        if (seen.has(el)) return;
        seen.add(el);
        if (first) {
          const r = el.getBoundingClientRect();
          if (r.top < vh() && r.bottom > 0) {
            el.dataset.revealed = "instant";
            return;
          }
        }
        reveal.observe(el);
      });
      scope.querySelectorAll<HTMLElement>("[data-loop]").forEach((el) => {
        if (seen.has(el)) return;
        seen.add(el);
        loops.observe(el);
      });
    };

    scan(document, true);
    root.classList.add("mo-on");

    const mo = new MutationObserver((list) => {
      for (const m of list)
        m.addedNodes.forEach((node) => {
          if (node.nodeType !== 1) return;
          const el = node as HTMLElement;
          if (el.matches("[data-reveal],[data-loop]")) scan(el.parentElement ?? document, false);
          else if (el.firstElementChild) scan(el, false);
        });
    });
    mo.observe(document.body, { childList: true, subtree: true });

    return () => {
      mo.disconnect();
      reveal.disconnect();
      loops.disconnect();
      root.classList.remove("mo-on");
    };
  }, []);

  return null;
}
