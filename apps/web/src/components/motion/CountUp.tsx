"use client";

import { useT } from "@/i18n/client";

/**
 * Prices and totals render instantly (no count-up): a price must read correctly the moment it appears.
 * Kept as a component so callers keep their tabular-number formatting.
 */
export function CountUp({ value, className, format = "bdt" }: { value: number; className?: string; format?: "bdt" | "int" }) {
  const { bdt, num } = useT();
  const text = format === "bdt" ? bdt(value) : num(Math.round(value));
  return <span className={"inline-block tabular-nums " + (className ?? "")}>{text}</span>;
}
