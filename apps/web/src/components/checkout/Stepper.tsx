"use client";

import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

export const STEPS = [
  { id: "address", label: "co.step.address" },
  { id: "delivery", label: "co.step.delivery" },
  { id: "payment", label: "co.step.payment" },
  { id: "review", label: "co.step.review" },
] as const satisfies readonly { id: string; label: MsgKey }[];

function DrawnCheck() {
  return (
    <svg viewBox="0 0 24 24" width="20" height="20" className="draw-check" aria-hidden="true">
      <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

// Desktop/tablet: dots on a line that fills to the current step; done steps swap numbers for drawn checks.
// Mobile: a compact progress pill.
export function Stepper({ step, reached, onGo }: { step: number; reached: number; onGo: (i: number) => void }) {
  const n = STEPS.length;
  const { t, digits } = useT();
  return (
    <nav aria-label={t("co.progress")}>
      {/* compact pill for phones */}
      <div className="sm:hidden">
        <div className="relative flex h-11 items-center justify-between overflow-hidden rounded-full bg-surface px-4 shadow-[var(--shadow-1)]">
          <span
            aria-hidden
            className="co-pill-fill opacity-15"
            style={{ transform: `scaleX(${(step + 1) / n})` }}
          />
          <span className="relative flex items-center gap-2 text-sm font-bold">
            <span className="grid h-6 w-6 place-items-center rounded-full bg-accent text-xs text-white">{digits(step + 1)}</span>
{t(STEPS[step].label)}
          </span>
          <span className="relative text-xs font-semibold text-ink-2" aria-current="step">
            {t("co.stepOf", { n: step + 1, total: n })}
          </span>
        </div>
        <div className="mt-2 flex gap-1.5 px-1" aria-hidden>
          {STEPS.map((s, i) => (
            <span key={s.id} className="relative h-1 flex-1 overflow-hidden rounded-full bg-surface-2">
              <span
                className="co-pill-fill"
                style={{ transform: `scaleX(${i <= step ? 1 : 0})`, transitionDelay: `${i * 60}ms` }}
              />
            </span>
          ))}
        </div>
      </div>

      {/* full stepper */}
      <ol className="co-steps hidden grid-cols-4 sm:grid">
        <span aria-hidden className="co-steps-line" style={{ left: "12.5%", right: "12.5%" }} />
        <span
          aria-hidden
          className="co-steps-fill"
          style={{ left: "12.5%", right: "12.5%", transform: `scaleX(${step / (n - 1)})` }}
        />
        {STEPS.map((s, i) => {
          const state = i < step ? "done" : i === step ? "current" : "todo";
          const can = i <= reached && i !== step;
          return (
            <li key={s.id} className="co-step relative flex flex-col items-center gap-2" data-state={state}>
              <button
                type="button"
                disabled={!can}
                onClick={() => onGo(i)}
                aria-current={i === step ? "step" : undefined}
                aria-label={t("co.stepAria", { label: t(s.label), n: i + 1, total: n }) + (state === "done" ? t("co.completed") : state === "current" ? t("co.current") : "")}
                className={"co-step-dot " + (state === "current" ? "ring-2 ring-accent/30 ring-offset-2 ring-offset-surface" : "") + (can ? " cursor-pointer" : "")}
              >
                {state === "done" ? <DrawnCheck /> : digits(i + 1)}
              </button>
              <span className={"text-xs font-semibold " + (state === "todo" ? "text-ink-3" : "text-ink")}>{t(s.label)}</span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
