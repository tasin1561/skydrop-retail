"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";

const START = "mo:nav-start";

/** Call before a programmatic router.push(href) so the bar shows for it too (skipped for the current URL). */
export function startRouteProgress(href?: string) {
  if (href) {
    const url = new URL(href, location.href);
    if (url.pathname === location.pathname && url.search === location.search) return;
  }
  window.dispatchEvent(new Event(START));
}

/**
 * Slim top progress bar. Starts on an internal link click (or startRouteProgress) and completes when
 * the URL commits. It only listens: it never intercepts or delays the navigation itself.
 */
export function RouteProgress() {
  const path = usePathname();
  const search = useSearchParams();
  const bar = useRef<HTMLDivElement>(null);
  const safety = useRef<ReturnType<typeof setTimeout> | null>(null);
  const running = useRef(false);

  useEffect(() => {
    const el = bar.current;
    if (!el) return;
    const start = () => {
      if (safety.current) clearTimeout(safety.current);
      running.current = true;
      el.dataset.state = "";
      void el.offsetWidth;
      el.dataset.state = "run";
      safety.current = setTimeout(() => finish(), 10000);
    };
    const finish = () => {
      if (!running.current) return;
      running.current = false;
      if (safety.current) clearTimeout(safety.current);
      const fill = el.firstElementChild as HTMLElement | null;
      if (fill) {
        // freeze the trickle where it is, then complete from there
        fill.style.transform = getComputedStyle(fill).transform;
        el.dataset.state = "done";
        void fill.offsetWidth;
        fill.style.transform = "";
      } else el.dataset.state = "done";
    };
    const onClick = (e: MouseEvent) => {
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const a = (e.target as Element | null)?.closest?.("a");
      if (!a || !a.href || a.target === "_blank" || a.hasAttribute("download")) return;
      const url = new URL(a.href, location.href);
      if (url.origin !== location.origin) return;
      if (url.pathname === location.pathname && url.search === location.search) return; // same page or hash jump
      start();
    };
    const onPop = () => start();
    (el as HTMLDivElement & { __finish?: () => void }).__finish = finish;
    document.addEventListener("click", onClick, true);
    window.addEventListener(START, start);
    window.addEventListener("popstate", onPop);
    return () => {
      document.removeEventListener("click", onClick, true);
      window.removeEventListener(START, start);
      window.removeEventListener("popstate", onPop);
      if (safety.current) clearTimeout(safety.current);
    };
  }, []);

  // URL committed: complete the bar.
  useEffect(() => {
    const el = bar.current as (HTMLDivElement & { __finish?: () => void }) | null;
    el?.__finish?.();
  }, [path, search]);

  return (
    <div ref={bar} className="mo-progress" aria-hidden="true">
      <i />
    </div>
  );
}
