"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

// Pill stepper with 44px targets; the number rolls up or down with the change.
export function QtyStepper({
  value,
  onChange,
  min = 1,
  max = 10,
  label,
}: {
  value: number;
  onChange: (n: number) => void;
  min?: number;
  max?: number;
  label: string;
}) {
  // "Adjust state during render" pattern: remember the previous value to pick a roll direction.
  const [prev, setPrev] = useState(value);
  const { t, digits } = useT();
  const [dir, setDir] = useState<"up" | "down" | "none">("none");
  if (prev !== value) {
    setDir(value > prev ? "up" : "down");
    setPrev(value);
  }
  return (
    <div className="co-stepper" role="group" aria-label={t("qty.for", { label })}>
      <button type="button" aria-label={t("qty.dec")} disabled={value <= min} onClick={() => onChange(value - 1)}>
        <Icon name="minus" size={16} stroke={2.4} />
      </button>
      <span className="co-num" aria-live="polite" aria-atomic="true">
        <span key={value} data-dir={dir} aria-hidden="true">
          {digits(String(value).padStart(2, "0"))}
        </span>
        <span className="sr-only">{digits(value)}</span>
      </span>
      <button type="button" aria-label={t("qty.inc")} disabled={value >= max} onClick={() => onChange(value + 1)}>
        <Icon name="plus" size={16} stroke={2.4} />
      </button>
    </div>
  );
}
