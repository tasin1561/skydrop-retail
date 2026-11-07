import { Icon } from "@/components/Icon";
import { formatBdt, priceBdt, PRICING } from "@/lib/pricing";
import type { Product } from "@/lib/types";
import { Accordion } from "./Accordion";

// Server-rendered figures inside a client accordion.
export function PriceBreakdown({ p, source }: { p: Product; source: string }) {
  const total = priceBdt(p);
  const converted = p.priceInr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const importPct = PRICING.importPct[p.category] ?? 0.1;
  const duty = converted * importPct;
  const service = total - Math.round(converted) - Math.round(duty);
  const { baseBdt, perKgBdt, freeAboveBdt } = PRICING.shipping;

  const rows: { icon: string; label: string; note: string; value: string }[] = [
    {
      icon: "tag",
      label: `Price on ${source}`,
      note: `₹${p.priceInr.toLocaleString("en-IN")} at ৳${PRICING.fxInrToBdt.toFixed(2)} per rupee, with FX protection`,
      value: formatBdt(converted),
    },
    {
      icon: "shield",
      label: "Import duty",
      note: `${Math.round(importPct * 100)}% for ${p.category}, paid by us at customs`,
      value: formatBdt(duty),
    },
    {
      icon: "spark",
      label: "Skydrop service",
      note: "Buying in India, checks and packing",
      value: formatBdt(service),
    },
  ];

  return (
    <Accordion
      title="Price breakdown"
      icon="receipt"
      meta="Import duty and service included. No surprises at the door."
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
          <span className="text-sm font-bold">Item price you pay</span>
          <span className="text-base font-extrabold tabular-nums">{formatBdt(total)}</span>
        </div>
        <div className="mt-2 flex items-start gap-3 px-1 py-1.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-surface text-accent shadow-[var(--shadow-1)]">
            <Icon name="truck" size={16} />
          </span>
          <p className="text-xs text-ink-2">
            <span className="font-semibold text-ink">Skydrop shipping is added in your cart by weight</span>: {formatBdt(baseBdt)} + {formatBdt(perKgBdt)}/kg per store
            shipment, free above {formatBdt(freeAboveBdt)}.
          </p>
        </div>
        <div className="flex items-start gap-3 px-1 py-1.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-surface text-accent shadow-[var(--shadow-1)]">
            <Icon name="refresh" size={16} />
          </span>
          <p className="text-xs text-ink-2">
            <span className="font-semibold text-ink">Prices are verified live at checkout.</span> If {source} changes the price, you see the new total before you pay.
          </p>
        </div>
      </div>
    </Accordion>
  );
}
