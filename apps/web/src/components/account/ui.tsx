"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";
import { EmptyArt } from "@/components/motion/EmptyArt";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { checkPhone, formatPhone, phoneDigits } from "./phone";
import "./account.css";
import { useT } from "@/i18n/client";

/* ---------------- Phone field (+880, live validation) ---------------- */

// For labels inside small shared pieces (Sheet, dialogs, toasts): reads the current language via a hook.
function useCommon() {
  return useT().t;
}

export function PhoneField({
  digits,
  onChange,
  label: labelProp,
  id,
  autoFocus,
  showErrors,
}: {
  digits: string;
  onChange: (digits: string) => void;
  label?: string;
  id?: string;
  autoFocus?: boolean;
  showErrors?: boolean;
}) {
  const auto = useId();
  const fid = id ?? auto;
  const { t } = useT();
  const label = labelProp ?? t("phone.label");
  const c = checkPhone(digits, t);
  // An incomplete number is only "warn" while typing; after a submit attempt it is an error.
  const tone = showErrors && c.tone === "warn" ? "bad" : showErrors && c.tone === "idle" ? "bad" : c.tone;
  const message = showErrors && c.tone === "idle" ? t("ui.enterPhone") : c.message;
  return (
    <div>
      <div
        className={
          "field ac-phone " + (tone === "good" ? "is-valid" : tone === "bad" ? "is-invalid" : tone === "warn" ? "ac-warn" : "")
        }
      >
        <input
          id={fid}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          placeholder=" "
          autoFocus={autoFocus}
          value={formatPhone(digits)}
          onChange={(e) => onChange(phoneDigits(e.target.value))}
          aria-invalid={tone === "bad"}
          aria-describedby={fid + "-help"}
          maxLength={14}
        />
        <label htmlFor={fid}>{label}</label>
        <span className="field-icon">
          <Icon name="phone" size={20} />
        </span>
        <span className="ac-prefix" aria-hidden>
          +880
        </span>
        {tone !== "idle" && (
          <span className="ac-status pop" data-tone={tone} aria-hidden>
            <Icon name={tone === "good" ? "check" : tone === "warn" ? "clock" : "x"} size={13} stroke={3} />
          </span>
        )}
      </div>
      <p id={fid + "-help"} className="ac-help" data-tone={tone} aria-live="polite">
        {message}
      </p>
    </div>
  );
}

/* ---------------- Dialog / bottom sheet with focus trap ---------------- */

export function useClosable(onClose: () => void, ms = 240) {
  const [closing, setClosing] = useState(false);
  const close = useCallback(() => {
    setClosing(true);
    const reduce = typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    setTimeout(onClose, reduce ? 0 : ms);
  }, [onClose, ms]);
  return { closing, close };
}

export function Sheet({
  title,
  description,
  icon = "info",
  onClose,
  children,
  size,
  tone,
}: {
  title: string;
  description?: string;
  icon?: string;
  onClose: () => void;
  children: (close: () => void) => React.ReactNode;
  size?: "sm";
  tone?: "bad";
}) {
  const tCommon = useCommon();
  const { closing, close } = useClosable(onClose);
  const ref = useRef<HTMLDivElement>(null);
  const tid = useId();

  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    const el = ref.current;
    const first = el?.querySelector<HTMLElement>("[data-autofocus]") ?? el?.querySelector<HTMLElement>("input,select,textarea,button");
    first?.focus();
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = overflow;
      prev?.focus?.();
    };
  }, []);

  function onKey(e: React.KeyboardEvent) {
    if (e.key === "Escape") {
      e.stopPropagation();
      close();
      return;
    }
    if (e.key !== "Tab" || !ref.current) return;
    const items = Array.from(
      ref.current.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea,[tabindex]:not([tabindex="-1"])'),
    );
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  return (
    <>
      <div className="ac-scrim" data-closing={closing} onClick={close} aria-hidden />
      <div
        ref={ref}
        role="dialog"
        aria-modal="true"
        aria-labelledby={tid}
        className="ac-panel"
        data-closing={closing}
        data-size={size}
        onKeyDown={onKey}
      >
        <span className="ac-grabber" aria-hidden />
        <div className="flex items-start gap-3 px-5 pb-2 pt-4">
          <span
            className="chip-icon is-active shrink-0"
            style={tone === "bad" ? { background: "var(--bad)", color: "#fff" } : undefined}
          >
            <Icon name={icon} size={18} />
          </span>
          <div className="min-w-0 flex-1">
            <h2 id={tid} className="text-lg font-extrabold tracking-tight">
              {title}
            </h2>
            {description && <p className="text-sm text-ink-2">{description}</p>}
          </div>
          <button
            type="button"
            onClick={close}
            aria-label={tCommon("common.close")}
            className="-mr-2 -mt-1 grid h-11 w-11 shrink-0 place-items-center rounded-full text-ink-2 transition hover:bg-surface-2"
          >
            <Icon name="x" size={18} />
          </button>
        </div>
        <div className="px-5 pb-5">{children(close)}</div>
      </div>
    </>
  );
}

/** Confirm dialog. Resolves through onConfirm, then closes. */
export function ConfirmDialog({
  title,
  body,
  confirmLabel,
  icon = "info",
  danger,
  onConfirm,
  onClose,
}: {
  title: string;
  body: string;
  confirmLabel: string;
  icon?: string;
  danger?: boolean;
  onConfirm: () => void;
  onClose: () => void;
}) {
  const tCommon = useCommon();
  return (
    <Sheet title={title} description={body} icon={icon} onClose={onClose} size="sm" tone={danger ? "bad" : undefined}>
      {(close) => (
        <div className="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" className="btn btn-ghost" onClick={close} data-autofocus>
            {tCommon("ui.keep")}
          </button>
          <button
            type="button"
            className={"btn " + (danger ? "" : "btn-primary")}
            style={danger ? { background: "var(--bad)", color: "#fff", boxShadow: "0 8px 24px color-mix(in srgb, var(--bad) 30%, transparent)" } : undefined}
            onClick={() => {
              onConfirm();
              close();
            }}
          >
            {confirmLabel}
          </button>
        </div>
      )}
    </Sheet>
  );
}

/* ---------------- Undo toast ---------------- */

export type UndoToastState = { id: number; title: string; body?: string; undo: () => void } | null;

export function UndoToast({ toast, onDone, ms = 5000 }: { toast: UndoToastState; onDone: () => void; ms?: number }) {
  const tCommon = useCommon();
  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(onDone, ms);
    return () => clearTimeout(t);
  }, [toast, onDone, ms]);
  return (
    <div className="ac-toast-wrap" aria-live="polite">
      {toast && (
        <div
          key={toast.id}
          className="toast-in pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-2xl bg-surface p-3 shadow-[var(--shadow-2)]"
          style={{ boxShadow: "0 12px 32px color-mix(in srgb, var(--accent) 22%, transparent)" }}
        >
          <div className="flex items-center gap-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent text-white">
              <Icon name="trash" size={17} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-bold">{toast.title}</p>
              {toast.body && <p className="truncate text-xs text-ink-2">{toast.body}</p>}
            </div>
            <button
              type="button"
              className="inline-flex min-h-11 items-center gap-1 rounded-full px-3 text-sm font-bold text-accent transition hover:bg-surface-2"
              onClick={() => {
                toast.undo();
                onDone();
              }}
            >
              <Icon name="refresh" size={15} /> {tCommon("ui.undo")}
            </button>
            <button
              type="button"
              aria-label={tCommon("toast.dismiss")}
              className="grid h-11 w-9 place-items-center rounded-full text-ink-3 hover:bg-surface-2"
              onClick={onDone}
            >
              <Icon name="x" size={16} />
            </button>
          </div>
          <span className="toast-bar absolute bottom-0 left-0 h-0.5 w-full bg-accent" style={{ animationDuration: ms + "ms" }} />
        </div>
      )}
    </div>
  );
}

/* ---------------- Tabs with a travelling pill ---------------- */

export function SegTabs<T extends string>({
  items,
  value,
  onChange,
  label,
  controls,
  dense,
}: {
  items: { id: T; label: string; icon?: string; count?: number }[];
  dense?: boolean;
  value: T;
  onChange: (v: T) => void;
  label: string;
  controls?: string;
}) {
  const idx = Math.max(0, items.findIndex((i) => i.id === value));
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  function onKey(e: React.KeyboardEvent) {
    let n = idx;
    if (e.key === "ArrowRight") n = (idx + 1) % items.length;
    else if (e.key === "ArrowLeft") n = (idx - 1 + items.length) % items.length;
    else if (e.key === "Home") n = 0;
    else if (e.key === "End") n = items.length - 1;
    else return;
    e.preventDefault();
    onChange(items[n].id);
    refs.current[n]?.focus();
  }
  return (
    <div role="tablist" aria-label={label} className="ac-tabs" data-dense={dense} style={{ ["--n" as string]: items.length }} onKeyDown={onKey}>
      <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: idx }} />
      {items.map((it, i) => (
        <button
          key={it.id}
          ref={(el) => {
            refs.current[i] = el;
          }}
          role="tab"
          type="button"
          id={`tab-${it.id}`}
          aria-selected={i === idx}
          aria-controls={controls}
          tabIndex={i === idx ? 0 : -1}
          className="ac-tab"
          onClick={() => onChange(it.id)}
        >
          {it.icon && <Icon name={it.icon} size={15} className="hidden sm:block" />}
          <span className="truncate">{it.label}</span>
          {typeof it.count === "number" && <span className="ac-tab-count">{String(it.count).padStart(2, "0")}</span>}
        </button>
      ))}
    </div>
  );
}

/* ---------------- Empty state with an animated parcel ---------------- */

export function EmptyState({
  title,
  body,
  action,
  icon = "package",
}: {
  title: string;
  body: string;
  action: { label: string; href: string } | { label: string; onClick: () => void };
  icon?: string;
}) {
  return (
    <div className="card fade-in relative isolate mx-auto flex max-w-md flex-col items-center overflow-hidden px-6 py-10 text-center">
      <EmptyArt kind={icon === "heart" ? "wishlist" : icon === "receipt" ? "orders" : icon === "search" ? "search" : "parcel"} className="relative mb-2" />
      <h2 className="text-lg font-extrabold tracking-tight">{title}</h2>
      <p className="mt-1 text-sm text-ink-2">{body}</p>
      {"href" in action ? (
        <Link href={action.href} className="btn btn-primary mt-5">
          {action.label} <Icon name="arrow" size={18} className="arrow" />
        </Link>
      ) : (
        <button type="button" onClick={action.onClick} className="btn btn-primary mt-5">
          {action.label} <Icon name="plus" size={18} />
        </button>
      )}
    </div>
  );
}

/* ---------------- Re-triggerable shake (keeps focus, no remount) ---------------- */

export function useShake() {
  const [on, setOn] = useState(false);
  const shake = useCallback(() => {
    setOn(false);
    requestAnimationFrame(() => requestAnimationFrame(() => setOn(true)));
  }, []);
  return { shakeClass: on ? "shake" : "", shake, onAnimationEnd: () => setOn(false) };
}

/* ---------------- Small pieces ---------------- */

export function PageHead({ title, sub, icon, back }: { title: string; sub?: string; icon: string; back?: { href: string; label: string } }) {
  return (
    <div className="mb-5">
      {back && (
        <Link href={back.href} className="group mb-2 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-ink-2 hover:text-accent">
          <Icon name="left" size={16} className="transition-transform group-hover:-translate-x-0.5" /> {back.label}
        </Link>
      )}
      <div className="flex items-center gap-3">
        <span className="chip-icon is-active h-11 w-11 rounded-2xl shadow-[var(--glow)]">
          <Icon name={icon} size={22} />
        </span>
        <div className="min-w-0">
          <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
          {sub && <p className="text-sm text-ink-2">{sub}</p>}
        </div>
      </div>
    </div>
  );
}

export function Spinner({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" className="ac-spin" aria-hidden>
      <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.3" strokeWidth="3" fill="none" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" fill="none" strokeLinecap="round" />
    </svg>
  );
}

/** Fires once after the client store has hydrated from localStorage. */
export function useNow(intervalMs: number) {
  const [now, setNow] = useState(0);
  useEffect(() => {
    const tick = () => setNow(Date.now());
    tick();
    const t = setInterval(() => {
      if (document.visibilityState === "visible") tick();
    }, intervalMs);
    document.addEventListener("visibilitychange", tick);
    return () => {
      clearInterval(t);
      document.removeEventListener("visibilitychange", tick);
    };
  }, [intervalMs]);
  return now;
}

export function LoadingBlock({ rows = 3 }: { rows?: number }) {
  const tCommon = useCommon();
  return (
    <div className="space-y-3" aria-busy="true" aria-label={tCommon("skel.loading")}>
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} className="skeleton h-24 rounded-2xl" />
      ))}
    </div>
  );
}
