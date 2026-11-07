"use client";

import { createContext, useCallback, useContext, useState } from "react";
import { Icon } from "./Icon";

type Toast = { id: number; kind: "success" | "info" | "error"; title: string; body?: string; action?: { label: string; href?: string; onClick?: () => void } };
type Push = (t: Omit<Toast, "id">) => void;

const ToastCtx = createContext<Push>(() => {});

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [list, setList] = useState<Toast[]>([]);
  const push = useCallback<Push>((t) => {
    const id = Date.now() + Math.random();
    setList((l) => [...l.slice(-2), { ...t, id }]);
    setTimeout(() => setList((l) => l.filter((x) => x.id !== id)), 3600);
  }, []);
  const tone = { success: "var(--good)", info: "var(--accent)", error: "var(--bad)" };
  const icon = { success: "check", info: "info", error: "x" };
  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 md:bottom-6 md:items-end">
        {list.map((t) => (
          <div
            key={t.id}
            className="toast-in pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-2xl bg-surface p-3 pr-10 shadow-[var(--shadow-2)]"
            style={{ boxShadow: `0 12px 32px color-mix(in srgb, ${tone[t.kind]} 25%, transparent)` }}
          >
            <div className="flex items-start gap-3">
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white" style={{ background: tone[t.kind] }}>
                <Icon name={icon[t.kind]} size={18} stroke={2.4} />
              </span>
              <div className="min-w-0">
                <p className="text-sm font-bold">{t.title}</p>
                {t.body && <p className="text-xs text-ink-2">{t.body}</p>}
                {t.action &&
                  (t.action.onClick ? (
                    <button
                      type="button"
                      onClick={() => {
                        t.action!.onClick!();
                        setList((l) => l.filter((x) => x.id !== t.id));
                      }}
                      className="mt-1 inline-block text-xs font-bold text-accent"
                    >
                      {t.action.label}
                    </button>
                  ) : (
                    <a href={t.action.href} className="mt-1 inline-block text-xs font-bold text-accent">
                      {t.action.label}
                    </a>
                  ))}
              </div>
            </div>
            <button
              className="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full text-ink-3 hover:bg-surface-2"
              aria-label="Dismiss"
              onClick={() => setList((l) => l.filter((x) => x.id !== t.id))}
            >
              <Icon name="x" size={16} />
            </button>
            <span className="toast-bar absolute bottom-0 left-0 h-0.5 w-full" style={{ background: tone[t.kind], animationDuration: "3600ms" }} />
          </div>
        ))}
      </div>
    </ToastCtx.Provider>
  );
}

export function useToast() {
  return useContext(ToastCtx);
}
