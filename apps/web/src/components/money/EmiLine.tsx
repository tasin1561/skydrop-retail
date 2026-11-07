"use client";

import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { emiFromMonthly, money, type EmiConfig } from "@/lib/money";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";

/** Product page: "EMI from ৳X/month" when the price is eligible for card EMI. */
export function EmiLine({ amount }: { amount: number }) {
  const on = useFeature("pay.emi");
  const { t, bdt } = useT();
  const [cfg, setCfg] = useState<EmiConfig | null>(null);
  useEffect(() => {
    if (!on) return;
    money
      .emiConfig()
      .then(setCfg)
      .catch(() => {});
  }, [on]);
  const from = on && cfg ? emiFromMonthly(cfg, amount) : null;
  if (!from || !cfg) return null;
  return (
    <p className="money-in flex items-center gap-1.5 text-xs text-ink-2">
      <Icon name="card" size={14} className="text-accent" />
      <span>
        {rich(t("emi.from", { count: Math.max(...cfg.tenures) }), { amount: <b className="tabular-nums text-ink">{bdt(from)}</b> })}
      </span>
    </p>
  );
}
