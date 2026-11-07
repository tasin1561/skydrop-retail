"use client";

export type VanState = "idle" | "busy" | "done";

// Place-order button: idle label -> the Skydrop van drives in and bobs while the real request runs (busy)
// -> it drives off and the done label rolls in only after a real success. A failed request returns to
// idle with `error` set: the button nudges and the caller shows the message and a "Try again" label.
// The accessible name stays the idle label throughout; progress is announced in a separate live region.
export function VanButton({
  state,
  onClick,
  idleLabel,
  doneLabel = "Order placed",
  busyText = "Placing your order",
  error,
  disabled,
  className,
}: {
  state: VanState;
  onClick: () => void;
  idleLabel: React.ReactNode;
  doneLabel?: string;
  busyText?: string;
  error?: boolean;
  disabled?: boolean;
  className?: string;
}) {
  return (
    <>
      <button
        type="button"
        data-state={state}
        data-error={error && state === "idle" ? "true" : undefined}
        aria-busy={state === "busy"}
        aria-disabled={disabled || state !== "idle"}
        onClick={() => {
          if (disabled || state !== "idle") return;
          onClick();
        }}
        className={"van-btn btn btn-coral " + (disabled ? "opacity-55 " : "") + (className ?? "")}
      >
        <span className="label idle inline-flex items-center gap-2">{idleLabel}</span>
        <span className="label done gap-2" aria-hidden="true">
          <span className="inline-flex items-center gap-2">
            <span className="draw-check inline-flex">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12l5 5 9-11" />
              </svg>
            </span>
            {doneLabel}
          </span>
        </span>
        <svg className="van" viewBox="0 0 46 26" aria-hidden="true">
          <g className="speed" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" opacity=".8">
            <path d="M1 9h6M3 14h5M1 19h4" />
          </g>
          <path d="M9 4h20v15H9z" fill="#fff" />
          <path d="M29 8h7l5 6v5H29z" fill="#fff" />
          <path d="M31 10h4.2l3 3.6H31z" fill="var(--brand-2)" opacity=".55" />
          <path d="M12 9h12" stroke="var(--brand-2)" strokeWidth="1.6" strokeLinecap="round" />
          <circle className="wheel" cx="15" cy="20" r="3.4" fill="#111114" stroke="#fff" strokeWidth="1.4" strokeDasharray="3 2" />
          <circle className="wheel" cx="35" cy="20" r="3.4" fill="#111114" stroke="#fff" strokeWidth="1.4" strokeDasharray="3 2" />
        </svg>
      </button>
      <span className="sr-only" aria-live="polite">
        {state === "busy" ? busyText : state === "done" ? doneLabel : ""}
      </span>
    </>
  );
}
