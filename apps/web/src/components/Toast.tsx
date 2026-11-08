"use client";

import Link from "next/link";
import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import { Icon } from "./Icon";
import { useT } from "@/i18n/client";

type Toast = {
  id: number;
  kind: "success" | "info" | "error";
  title: string;
  body?: string;
  /** optional icon override for the badge (defaults by kind) */
  icon?: string;
  action?: { label: string; href?: string; onClick?: () => void };
};
type Push = (t: Omit<Toast, "id">) => void;

const ToastCtx = createContext<Push>(() => {});
const LIFE = 4200;

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [list, setList] = useState<Toast[]>([]);
  const push = useCallback<Push>((t) => {
    const id = Date.now() + Math.random();
    setList((l) => [...l.slice(-2), { ...t, id }]);
  }, []);
  const remove = useCallback((id: number) => setList((l) => l.filter((x) => x.id !== id)), []);
  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 md:bottom-auto md:top-28 md:items-end">
        {list.map((t) => (
          <ToastItem key={t.id} t={t} onGone={remove} />
        ))}
      </div>
    </ToastCtx.Provider>
  );
}

const TONE = { success: "var(--good)", info: "var(--accent)", error: "var(--bad)" };
const ICON = { success: "check", info: "info", error: "x" };

/**
 * A toast is a small designed object: icon badge, title, body, action, close, a draining bar.
 * Hover/focus pauses it; swipe sideways (or tap close) dismisses it.
 */
function ToastItem({ t, onGone }: { t: Toast; onGone: (id: number) => void }) {
  const ref = useRef<HTMLDivElement>(null);
  const { t: tr } = useT();
  const left = useRef(LIFE);
  const started = useRef(0);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const drag = useRef<{ x: number; dx: number; id: number } | null>(null);

  const dismiss = useCallback(
    (dir = 1) => {
      const el = ref.current;
      if (timer.current) clearTimeout(timer.current);
      if (!el) return onGone(t.id);
      el.style.setProperty("--out", `${dir * 110}%`);
      el.style.transform = "";
      el.dataset.out = "true";
      setTimeout(() => onGone(t.id), 260);
    },
    [onGone, t.id],
  );

  const run = useCallback(() => {
    started.current = Date.now();
    timer.current = setTimeout(() => dismiss(1), left.current);
  }, [dismiss]);
  const pause = () => {
    if (!timer.current) return;
    clearTimeout(timer.current);
    timer.current = null;
    left.current = Math.max(600, left.current - (Date.now() - started.current));
  };
  const resume = () => {
    if (!timer.current && !drag.current && ref.current?.dataset.out !== "true") run();
  };

  useEffect(() => {
    run();
    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, [run]);

  const tone = TONE[t.kind];
  return (
    <div
      ref={ref}
      role={t.kind === "error" ? "alert" : "status"}
      className="mo-toast toast-in pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-2xl bg-surface p-3 pr-11 ring-1 ring-line"
      style={{ boxShadow: `var(--shadow-2), 0 12px 32px -6px color-mix(in srgb, ${tone} 35%, transparent)` }}
      onMouseEnter={pause}
      onMouseLeave={resume}
      onFocus={pause}
      onBlur={resume}
      onPointerDown={(e) => {
        if ((e.target as HTMLElement).closest("button,a")) return;
        drag.current = { x: e.clientX, dx: 0, id: e.pointerId };
        pause();
      }}
      onPointerMove={(e) => {
        const d = drag.current;
        const el = ref.current;
        if (!d || !el || e.pointerId !== d.id) return;
        d.dx = e.clientX - d.x;
        if (Math.abs(d.dx) > 4) {
          el.dataset.drag = "true";
          el.setPointerCapture?.(e.pointerId);
          el.style.transform = `translateX(${d.dx}px)`;
          el.style.opacity = String(Math.max(0.3, 1 - Math.abs(d.dx) / 260));
        }
      }}
      onPointerUp={() => {
        const d = drag.current;
        const el = ref.current;
        drag.current = null;
        if (!d || !el) return;
        el.dataset.drag = "false";
        el.style.opacity = "";
        if (Math.abs(d.dx) > 80) dismiss(Math.sign(d.dx));
        else {
          el.style.transform = "";
          resume();
        }
      }}
      onPointerCancel={() => {
        drag.current = null;
        if (ref.current) {
          ref.current.dataset.drag = "false";
          ref.current.style.transform = "";
          ref.current.style.opacity = "";
        }
        resume();
      }}
    >
      <div className="flex items-start gap-3">
        <span
          className="pop grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white"
          style={{ background: tone }}
        >
          <Icon name={t.icon ?? ICON[t.kind]} size={18} stroke={2.4} fill={t.icon === "heart" ? "currentColor" : "none"} />
        </span>
        <div className="min-w-0 flex-1 py-0.5">
          <p className="text-sm font-bold leading-snug">{t.title}</p>
          {t.body && <p className="mt-0.5 line-clamp-2 text-xs text-ink-2">{t.body}</p>}
          {t.action &&
            (t.action.onClick ? (
              <button
                type="button"
                onClick={() => {
                  t.action!.onClick!();
                  dismiss(1);
                }}
                className="-ml-2 mt-0.5 inline-flex min-h-9 items-center gap-1 rounded-full px-2 text-xs font-bold text-accent hover:bg-[var(--accent-soft)]"
              >
                {t.action.label} <Icon name="right" size={13} stroke={2.4} />
              </button>
            ) : (
              <Link
                href={t.action.href ?? "/"}
                onClick={() => dismiss(1)}
                className="-ml-2 mt-0.5 inline-flex min-h-9 items-center gap-1 rounded-full px-2 text-xs font-bold text-accent hover:bg-[var(--accent-soft)]"
              >
                {t.action.label} <Icon name="right" size={13} stroke={2.4} />
              </Link>
            ))}
        </div>
      </div>
      <button
        className="absolute right-1 top-1 grid h-11 w-11 place-items-center rounded-full text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink"
        aria-label={tr("toast.dismiss")}
        onClick={() => dismiss(1)}
      >
        <Icon name="x" size={16} />
      </button>
      <span className="toast-bar absolute bottom-0 left-0 h-[3px] w-full opacity-80" style={{ background: tone, animationDuration: `${LIFE}ms` }} />
    </div>
  );
}

export function useToast() {
  return useContext(ToastCtx);
}
