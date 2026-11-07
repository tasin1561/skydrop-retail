"use client";

import { useT } from "@/i18n/client";

export type VanState = "idle" | "busy" | "done";

// Place-order button: idle label -> a spinner while the real request runs (busy) -> a drawn check and
// the done label only after a real success. A failed request returns to idle with `error` set: the
// button gets a calm red ring and the caller shows the message and a "Try again" label.
// The accessible name stays the idle label throughout; progress is announced in a separate live region.
export function VanButton({
  state,
  onClick,
  idleLabel,
  doneLabel: doneProp,
  busyText: busyProp,
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
  const { t } = useT();
  const doneLabel = doneProp ?? t("van.placed");
  const busyText = busyProp ?? t("van.placing");
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
        <span className="busy" aria-hidden="true">
          <i />
        </span>
      </button>
      <span className="sr-only" aria-live="polite">
        {state === "busy" ? busyText : state === "done" ? doneLabel : ""}
      </span>
    </>
  );
}
