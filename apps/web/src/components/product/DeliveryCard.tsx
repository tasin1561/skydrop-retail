import { Icon } from "@/components/Icon";
import { deliveryWindow } from "@/lib/pricing";
import { getT } from "@/i18n/server";
import { rich } from "@/i18n/rich";

function addDays(d: Date, n: number) {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
}

export async function DeliveryCard({ source, kolkata, weightKg }: { source: string; kolkata?: string | null; weightKg: number }) {
  const { t, date, digits } = await getT();
  const fmt = (d: Date) => date(d, { day: "numeric", month: "short", timeZone: "Asia/Dhaka" });
  const w = deliveryWindow();
  const now = new Date();
  const steps = [
    { icon: "bag", title: t("dc.boughtFrom"), body: t("dc.boughtFromBody", { source }) },
    { icon: "pin", title: t("dc.hub"), body: t("dc.hubBody") },
    { icon: "home", title: t("dc.door"), body: t("dc.doorBody") },
  ];
  return (
    <section aria-labelledby="pd-delivery" className="card p-4">
      <div className="mb-3 flex items-start gap-3">
        <span className="chip-icon shrink-0">
          <Icon name="truck" size={19} />
        </span>
        <div>
          <h2 id="pd-delivery" className="text-[15px] font-bold leading-tight">
            {t("dc.deliveredIn", { min: w.min, max: w.max })}
          </h2>
          <p className="text-xs text-ink-2">
            {t("dc.eta")} <span className="font-semibold text-ink">{fmt(addDays(now, w.min))} - {fmt(addDays(now, w.max))}</span>
          </p>
        </div>
      </div>

      <ol className="relative grid grid-cols-3 gap-1" data-reveal="fade" data-loop="" suppressHydrationWarning>
        <span aria-hidden className="pd-route-line absolute left-[16.6%] right-[16.6%] top-[19px] h-[2px]" />
        <span aria-hidden className="mo-route-fill absolute left-[16.6%] right-[16.6%] top-[19px] h-[2px] rounded-full bg-accent opacity-70" />
        <span aria-hidden className="absolute left-1/3 top-[11px] -ml-2 text-accent">
          <span className="pd-route-plane inline-block">
            <Icon name="plane" size={16} />
          </span>
        </span>
        {steps.map((s, i) => (
          <li key={s.title} className="mo-route-step relative flex flex-col items-center text-center" style={{ ["--i" as string]: i }}>
            <span
              className={
                "relative z-[1] grid h-10 w-10 place-items-center rounded-[10px] shadow-[var(--shadow-1)] " +
                (i === 2 ? "bg-primary text-on-primary" : "bg-surface text-ink-2")
              }
            >
              <Icon name={s.icon} size={18} />
            </span>
            <span className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink-3">
              {digits(i + 1)}. {s.title}
            </span>
            <span className="text-xs font-semibold leading-snug text-ink">{s.body}</span>
          </li>
        ))}
      </ol>

      <ul className="mt-4 flex flex-col gap-2 text-xs text-ink-2">
        {kolkata && (
          <li className="flex items-center gap-2">
            <Icon name="clock" size={15} className="shrink-0 text-accent" />
            <span>
              {rich(t("dc.kolkata"), { date: <span className="font-semibold text-ink">{kolkata}</span> })}
            </span>
          </li>
        )}
        <li className="flex items-center gap-2">
          <Icon name="package" size={15} className="shrink-0 text-accent" />
          <span>
            {rich(t("dc.weight"), { weight: <span className="font-semibold text-ink">{weightKg < 1 ? t("dc.grams", { n: Math.round(weightKg * 1000) }) : t("dc.kg", { n: weightKg })}</span> })}
          </span>
        </li>
        <li className="flex items-center gap-2">
          <Icon name="cash" size={15} className="shrink-0 text-accent" />
          <span>{t("dc.pay")}</span>
        </li>
      </ul>
    </section>
  );
}

export async function StockLine({ stock, demo }: { stock?: string; demo?: boolean }) {
  const { t } = await getT();
  const s = (stock ?? "").toLowerCase();
  let tone: "good" | "warn" | "bad" | "info" = "info";
  let word = t("stock.checked");
  let icon = "info";
  if (/out of stock|unavailable/.test(s)) {
    tone = "bad";
    word = t("stock.out");
    icon = "x";
  } else if (/only|few|left/.test(s)) {
    tone = "warn";
    word = stock!;
    icon = "clock";
  } else if (/in stock/.test(s)) {
    tone = "good";
    word = t("stock.in");
    icon = "check";
  } else if (demo) {
    word = t("stock.demo");
  }
  const color = { good: "var(--good)", warn: "var(--warn)", bad: "var(--bad)", info: "var(--ink-2)" }[tone];
  return (
    <p className="inline-flex items-center gap-1.5 text-sm font-medium" style={{ color }}>
      <Icon name={icon} size={15} stroke={2.2} />
      {word}
      {tone === "good" && <span className="font-normal text-ink-2">{t("stock.atStore")}</span>}
    </p>
  );
}

export function isUnavailableStock(stock?: string) {
  return /out of stock|unavailable/i.test(stock ?? "");
}
