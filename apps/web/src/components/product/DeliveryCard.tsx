import { Icon } from "@/components/Icon";
import { deliveryWindow } from "@/lib/pricing";

function addDays(d: Date, n: number) {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
}
const fmt = (d: Date) => d.toLocaleDateString("en-GB", { day: "numeric", month: "short", timeZone: "Asia/Dhaka" });

export function DeliveryCard({ source, kolkata, weightKg }: { source: string; kolkata?: string | null; weightKg: number }) {
  const w = deliveryWindow();
  const now = new Date();
  const steps = [
    { icon: "bag", title: "Bought from", body: `${source}, India` },
    { icon: "pin", title: "Skydrop hub", body: "Kolkata, checked & packed" },
    { icon: "home", title: "Your door", body: "Anywhere in Bangladesh" },
  ];
  return (
    <section aria-labelledby="pd-delivery" className="card p-4">
      <div className="mb-3 flex items-start gap-3">
        <span className="chip-icon shrink-0">
          <Icon name="truck" size={19} />
        </span>
        <div>
          <h2 id="pd-delivery" className="text-[15px] font-bold leading-tight">
            Delivered in {w.min}-{w.max} days
          </h2>
          <p className="text-xs text-ink-2">
            Estimated arrival <span className="font-semibold text-ink">{fmt(addDays(now, w.min))} - {fmt(addDays(now, w.max))}</span>
          </p>
        </div>
      </div>

      <ol className="relative grid grid-cols-3 gap-1" data-reveal="fade" data-loop="">
        <span aria-hidden className="pd-route-line absolute left-[16.6%] right-[16.6%] top-[19px] h-[2px]" />
        <span aria-hidden className="mo-route-fill absolute left-[16.6%] right-[16.6%] top-[19px] h-[2px] rounded-full bg-gradient-to-r from-[var(--accent)] to-[var(--accent-2)] opacity-70" />
        <span aria-hidden className="absolute left-1/3 top-[11px] -ml-2 text-accent">
          <span className="pd-route-plane inline-block">
            <Icon name="plane" size={16} />
          </span>
        </span>
        {steps.map((s, i) => (
          <li key={s.title} className="mo-route-step relative flex flex-col items-center text-center" style={{ ["--i" as string]: i }}>
            <span
              className={
                "relative z-[1] grid h-10 w-10 place-items-center rounded-[14px] shadow-[var(--shadow-1)] " +
                (i === 2 ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-accent")
              }
            >
              <Icon name={s.icon} size={18} />
            </span>
            <span className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink-3">
              {i + 1}. {s.title}
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
              Reaches our Kolkata hub by <span className="font-semibold text-ink">{kolkata}</span>
            </span>
          </li>
        )}
        <li className="flex items-center gap-2">
          <Icon name="package" size={15} className="shrink-0 text-accent" />
          <span>
            Shipping weight about <span className="font-semibold text-ink">{weightKg < 1 ? `${Math.round(weightKg * 1000)} g` : `${weightKg} kg`}</span>, charged in cart
          </span>
        </li>
        <li className="flex items-center gap-2">
          <Icon name="cash" size={15} className="shrink-0 text-accent" />
          <span>Cash on delivery, bKash, Nagad and cards at checkout</span>
        </li>
      </ul>
    </section>
  );
}

export function StockLine({ stock, demo }: { stock?: string; demo?: boolean }) {
  const s = (stock ?? "").toLowerCase();
  let tone: "good" | "warn" | "bad" | "info" = "info";
  let word = "Availability checked at checkout";
  let icon = "info";
  if (/out of stock|unavailable/.test(s)) {
    tone = "bad";
    word = "Out of stock";
    icon = "x";
  } else if (/only|few|left/.test(s)) {
    tone = "warn";
    word = stock!;
    icon = "clock";
  } else if (/in stock/.test(s)) {
    tone = "good";
    word = "In stock";
    icon = "check";
  } else if (demo) {
    word = "Demo listing, not for sale yet";
  }
  const color = { good: "var(--good)", warn: "var(--warn)", bad: "var(--bad)", info: "var(--ink-2)" }[tone];
  return (
    <p className="inline-flex items-center gap-2 text-sm font-semibold" style={{ color }}>
      <span
        className="grid h-6 w-6 place-items-center rounded-full"
        style={{ background: `color-mix(in srgb, ${color} 14%, transparent)` }}
      >
        <Icon name={icon} size={14} stroke={2.6} />
      </span>
      {word}
      {tone === "good" && <span className="font-normal text-ink-2">at the store in India</span>}
    </p>
  );
}

export function isUnavailableStock(stock?: string) {
  return /out of stock|unavailable/i.test(stock ?? "");
}
