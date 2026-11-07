"use client";

import "./store.css";
import { useCallback, useEffect, useRef, useState, type ReactNode, type RefObject } from "react";
import { createPortal } from "react-dom";

const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
const reduceMotion = () => typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/** Plays the 160ms exit, then calls `done`. Instant with reduced motion. */
export function useClosing(done: () => void) {
  const [closing, setClosing] = useState(false);
  const fn = useRef(done);
  useEffect(() => {
    fn.current = done;
  }, [done]);
  const close = useCallback(() => {
    if (reduceMotion()) {
      fn.current();
      return;
    }
    setClosing(true);
    setTimeout(() => fn.current(), 160);
  }, []);
  return { closing, close };
}

/**
 * Only rendered client-side after an interaction or effect (it portals to document.body).
 * Accessible modal: bottom sheet on phones, centred card from 640px. Focus moves in (initialFocus or the panel),
 * Tab is trapped, Esc calls onEscape, the page behind does not scroll (no layout shift: the scrollbar width is
 * padded back) and focus returns to where it was when the dialog closes.
 */
export function ModalShell({
  labelledBy,
  describedBy,
  onEscape,
  onBackdrop,
  closing,
  initialFocus,
  width = "max-w-lg",
  children,
}: {
  labelledBy: string;
  describedBy?: string;
  onEscape: () => void;
  onBackdrop?: () => void;
  closing: boolean;
  initialFocus?: RefObject<HTMLElement | null>;
  width?: string;
  children: ReactNode;
}) {
  const panel = useRef<HTMLDivElement>(null);
  const esc = useRef(onEscape);
  useEffect(() => {
    esc.current = onEscape;
  }, [onEscape]);

  useEffect(() => {
    const prev = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    (initialFocus?.current ?? panel.current)?.focus({ preventScroll: true });

    const body = document.body;
    const gap = window.innerWidth - document.documentElement.clientWidth;
    const prevOverflow = body.style.overflow;
    const prevPad = body.style.paddingRight;
    body.style.overflow = "hidden";
    if (gap > 0) body.style.paddingRight = `${gap}px`;

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault();
        e.stopPropagation();
        esc.current();
        return;
      }
      if (e.key !== "Tab" || !panel.current) return;
      const items = Array.from(panel.current.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((el) => el.offsetParent !== null);
      if (!items.length) {
        e.preventDefault();
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      if (!panel.current.contains(active)) {
        e.preventDefault();
        first.focus();
      } else if (e.shiftKey && active === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener("keydown", onKey, true);
    return () => {
      document.removeEventListener("keydown", onKey, true);
      body.style.overflow = prevOverflow;
      body.style.paddingRight = prevPad;
      const back = prev && prev !== document.body && prev.isConnected ? prev : document.getElementById("main");
      back?.focus({ preventScroll: true });
    };
    // mount/unmount only: initialFocus is a stable ref
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // portalled to <body>: the sticky header is its own stacking context and would sit under the bottom tab bar
  return createPortal(
    <div className="sp-root fixed inset-0 z-[95] flex items-end justify-center sm:items-center sm:p-6" data-state={closing ? "closing" : "open"}>
      <div aria-hidden className="sp-backdrop absolute inset-0 bg-black/45" onClick={onBackdrop} />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-labelledby={labelledBy}
        aria-describedby={describedBy}
        tabIndex={-1}
        className={
          "sp-panel relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[20px] bg-surface pb-[env(safe-area-inset-bottom)] text-ink shadow-[var(--shadow-2)] outline-none sm:rounded-[16px] sm:pb-0 " +
          width
        }
      >
        {children}
      </div>
    </div>,
    document.body,
  );
}
