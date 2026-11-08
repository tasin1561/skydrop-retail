"use client";

import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { UNBOXING_FEE_BDT } from "@/lib/fulfilment";
import "./fulfilment.css";
import { useT } from "@/i18n/client";

/** Client estimate of the paid-unboxing fee (default ৳150 per store shipment). The API quote/order is authoritative. */
export function unboxingFeeFor(selected: boolean, shipments: number) {
  return selected ? UNBOXING_FEE_BDT * Math.max(1, shipments) : 0;
}

/** Checkout option: "Unboxing check at our Kolkata hub". Hidden when the "unboxing" feature is off. */
export function UnboxingToggle({ checked, onChange, shipments, disabled }: { checked: boolean; onChange: (v: boolean) => void; shipments: number; disabled?: boolean }) {
  const on = useFeature("unboxing");
  const { t, bdt } = useT();
  if (!on) return null;
  const fee = unboxingFeeFor(true, shipments);
  return (
    <label className={"co-option mt-3 items-center " + (checked ? "is-active" : "")}>
      <input type="checkbox" className="sr-only" checked={checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
      <span className="chip-icon shrink-0">
        <Icon name="package" size={18} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-bold">{t("ub.title")}</span>
        <span className="block text-xs text-ink-2">
          {t("ub.desc", { fee: bdt(fee) })}
{shipments > 1 ? t("ub.perStore", { fee: bdt(UNBOXING_FEE_BDT), count: shipments }) : ""}
        </span>
      </span>
      <span className={"rounded-full px-2.5 py-1 text-[11px] font-extrabold " + (checked ? "bg-primary text-on-primary" : "bg-surface-2 text-ink-2")}>
        {checked ? t("f.on") : t("f.off")}
      </span>
    </label>
  );
}
