"use client";

import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { formatBdt } from "@/lib/pricing";
import { UNBOXING_FEE_BDT } from "@/lib/fulfilment";
import "./fulfilment.css";

/** Client estimate of the paid-unboxing fee (default ৳150 per store shipment). The API quote/order is authoritative. */
export function unboxingFeeFor(selected: boolean, shipments: number) {
  return selected ? UNBOXING_FEE_BDT * Math.max(1, shipments) : 0;
}

/** Checkout option: "Unboxing check at our Kolkata hub". Hidden when the "unboxing" feature is off. */
export function UnboxingToggle({ checked, onChange, shipments, disabled }: { checked: boolean; onChange: (v: boolean) => void; shipments: number; disabled?: boolean }) {
  const on = useFeature("unboxing");
  if (!on) return null;
  const fee = unboxingFeeFor(true, shipments);
  return (
    <label className={"co-option mt-3 items-center " + (checked ? "is-active" : "")}>
      <input type="checkbox" className="sr-only" checked={checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
      <span className="chip-icon shrink-0">
        <Icon name="package" size={18} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-bold">Unboxing check at our Kolkata hub</span>
        <span className="block text-xs text-ink-2">
          We open it, photograph/video it and you approve before it ships. {formatBdt(fee)}
          {shipments > 1 ? ` (${formatBdt(UNBOXING_FEE_BDT)} × ${shipments} store shipments)` : ""}
        </span>
      </span>
      <span className={"rounded-full px-2.5 py-1 text-[11px] font-extrabold " + (checked ? "bg-accent text-white" : "bg-surface-2 text-ink-2")}>
        {checked ? "ON" : "OFF"}
      </span>
    </label>
  );
}
