"use client";

import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { emiFromMonthly, money, type EmiConfig } from "@/lib/money";
import { formatBdt } from "@/lib/pricing";

/** Product page: "EMI from ৳X/month" when the price is eligible for card EMI. */
export function EmiLine({ amount }: { amount: number }) {
  const on = useFeature("pay.emi");
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
        Card EMI from <b className="tabular-nums text-ink">{formatBdt(from)}/month</b> · up to {Math.max(...cfg.tenures)} months
      </span>
    </p>
  );
}
