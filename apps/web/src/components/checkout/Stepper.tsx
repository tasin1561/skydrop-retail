"use client";

export const STEPS = [
  { id: "address", label: "Address" },
  { id: "delivery", label: "Delivery" },
  { id: "payment", label: "Payment" },
  { id: "review", label: "Review" },
] as const;

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
  return (
    <nav aria-label="Checkout progress">
      {/* compact pill for phones */}
      <div className="sm:hidden">
        <div className="relative flex h-11 items-center justify-between overflow-hidden rounded-full bg-surface px-4 shadow-[var(--shadow-1)]">
          <span
            aria-hidden
            className="co-pill-fill opacity-15"
            style={{ transform: `scaleX(${(step + 1) / n})` }}
          />
          <span className="relative flex items-center gap-2 text-sm font-bold">
            <span className="grid h-6 w-6 place-items-center rounded-full bg-accent text-xs text-white">{step + 1}</span>
            {STEPS[step].label}
          </span>
          <span className="relative text-xs font-semibold text-ink-2" aria-current="step">
            Step {step + 1} of {n}
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
                aria-label={`${s.label}, step ${i + 1} of ${n}${state === "done" ? ", completed" : state === "current" ? ", current" : ""}`}
                className={"co-step-dot " + (state === "current" ? "pulse-dot" : "") + (can ? " cursor-pointer" : "")}
              >
                {state === "done" ? <DrawnCheck /> : i + 1}
              </button>
              <span className={"text-xs font-semibold " + (state === "todo" ? "text-ink-3" : "text-ink")}>{s.label}</span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
