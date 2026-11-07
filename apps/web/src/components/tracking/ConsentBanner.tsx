"use client";

import { useEffect, useRef } from "react";
import { setConsent, type Consent } from "@/lib/track/consent";
import { useT } from "@/i18n/client";

/** Small floating cookie card. Non-modal: the shop stays usable while it is open. */
export function ConsentBanner({ current, onDone }: { current: Consent | null; onDone: () => void }) {
  const { t } = useT();
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    // when reopened from the footer, move focus to the card
    if (current) ref.current?.focus();
  }, [current]);

  const choose = (c: Consent) => {
    setConsent(c);
    onDone();
  };

  return (
    <div
      ref={ref}
      tabIndex={-1}
      role="dialog"
      aria-modal="false"
      aria-labelledby="consent-h"
      aria-describedby="consent-d"
      className="rise fixed inset-x-3 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-[60] mx-auto max-w-sm rounded-[12px] border border-line/70 bg-surface/90 p-4 text-ink shadow-[var(--shadow-2)] outline-none backdrop-blur-xl sm:inset-x-auto sm:left-5 sm:bottom-5 md:bottom-5"
    >
      <div className="flex items-start gap-3">
        <span aria-hidden className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--accent-soft)] text-accent">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 3a9 9 0 1 0 9 9 4 4 0 0 1-4-4 4 4 0 0 1-5-5Z" />
            <circle cx="8.5" cy="12.5" r=".8" fill="currentColor" />
            <circle cx="12.5" cy="16.5" r=".8" fill="currentColor" />
            <circle cx="15.5" cy="12" r=".8" fill="currentColor" />
          </svg>
        </span>
        <div className="min-w-0">
          <h2 id="consent-h" className="text-sm font-extrabold">
            {t("cs.title")}
          </h2>
          <p id="consent-d" className="mt-1 text-xs leading-relaxed text-ink-2">
            {t("cs.body")}
          </p>
        </div>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2">
        <button type="button" onClick={() => choose("essential")} className={"btn btn-ghost min-h-10 px-3 text-sm " + (current === "essential" ? "ring-2 ring-accent/40" : "")}>
          {t("cs.essential")}
        </button>
        <button type="button" onClick={() => choose("all")} className="btn btn-primary min-h-10 px-3 text-sm">
          {t("cs.accept")}
        </button>
      </div>
    </div>
  );
}
