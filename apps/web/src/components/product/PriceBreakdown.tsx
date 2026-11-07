import { Icon } from "@/components/Icon";
import { priceBdt, PRICING } from "@/lib/pricing";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";
import type { Product } from "@/lib/types";
import { Accordion } from "./Accordion";

// Server-rendered figures inside a client accordion.
export async function PriceBreakdown({ p, source }: { p: Product; source: string }) {
  const { t, bdt, inr, digits } = await getT();
  const formatBdt = bdt;
  const total = priceBdt(p);
  const converted = p.priceInr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const importPct = PRICING.importPct[p.category] ?? 0.1;
  const duty = converted * importPct;
  const service = total - Math.round(converted) - Math.round(duty);
  const { baseBdt, perKgBdt, freeAboveBdt } = PRICING.shipping;

  const rows: { icon: string; label: string; note: string; value: string }[] = [
    {
      icon: "tag",
      label: t("pb.priceOn", { source }),
      note: t("pb.fxNote", { inr: inr(p.priceInr), rate: "৳" + digits(PRICING.fxInrToBdt.toFixed(2)) }),
      value: formatBdt(converted),
    },
    {
      icon: "shield",
      label: t("pb.duty"),
      note: t("pb.dutyNote", { pct: Math.round(importPct * 100), category: t(`cat.${p.category}` as MsgKey) }),
      value: formatBdt(duty),
    },
    {
      icon: "spark",
      label: t("pb.service"),
      note: t("pb.serviceNote"),
      value: formatBdt(service),
    },
  ];

  return (
    <Accordion
      title={t("pb.title")}
      icon="receipt"
      meta={t("pb.meta")}
      className="rounded-2xl bg-surface-2/60 px-3"
    >
      <div className="flex flex-col gap-1 pl-1">
        {rows.map((r) => (
          <div key={r.label} className="flex items-start gap-3 rounded-xl px-1 py-2">
            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-surface text-accent shadow-[var(--shadow-1)]">
              <Icon name={r.icon} size={16} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold">{r.label}</p>
              <p className="text-xs text-ink-2">{r.note}</p>
            </div>
            <span className="text-sm font-semibold tabular-nums">{r.value}</span>
          </div>
        ))}
        <div className="mt-1 flex items-center justify-between rounded-xl bg-surface px-3 py-2.5 shadow-[var(--shadow-1)]">
          <span className="text-sm font-bold">{t("pb.youPay")}</span>
          <span className="text-base font-extrabold tabular-nums">{formatBdt(total)}</span>
        </div>
        <div className="mt-2 flex items-start gap-3 px-1 py-1.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-surface text-accent shadow-[var(--shadow-1)]">
            <Icon name="truck" size={16} />
          </span>
          <p className="text-xs text-ink-2">
            <span className="font-semibold text-ink">{t("pb.shipping")}</span>: {t("pb.shippingRule", { base: formatBdt(baseBdt), perKg: formatBdt(perKgBdt), free: formatBdt(freeAboveBdt) })}
          </p>
        </div>
        <div className="flex items-start gap-3 px-1 py-1.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-surface text-accent shadow-[var(--shadow-1)]">
            <Icon name="refresh" size={16} />
          </span>
          <p className="text-xs text-ink-2">
            <span className="font-semibold text-ink">{t("pb.verified")}</span> {t("pb.verifiedNote", { source })}
          </p>
        </div>
      </div>
    </Accordion>
  );
}
