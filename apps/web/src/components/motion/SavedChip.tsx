"use client";

import { CountUp } from "./CountUp";

/** "You saved ৳X" chip. Shows only when a real discount applies to this cart. */
export function SavedChip({ amount }: { amount: number }) {
  if (amount <= 0) return null;
  return (
    <p
      className="mo-saved inline-flex items-center gap-1.5 rounded-full bg-[color-mix(in_srgb,var(--good)_14%,transparent)] py-1 pl-1.5 pr-3 text-xs font-bold text-good"
      aria-live="polite"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" className="shrink-0">
        <circle cx="12" cy="12" r="11" fill="currentColor" opacity=".16" />
        <path d="M7 12.5l3 3 7-7.5" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
      You saved <CountUp value={amount} />
    </p>
  );
}
