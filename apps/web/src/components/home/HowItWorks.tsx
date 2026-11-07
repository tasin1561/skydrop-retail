import { Icon } from "../Icon";

const STEPS = [
  { icon: "search", title: "Pick from Indian stores", text: "Search amazon.in, flipkart.com and bigbasket.com products, priced in taka." },
  { icon: "shield", title: "We verify and buy", text: "Live price and stock check at checkout, then we buy it in India for you." },
  { icon: "package", title: "Skydrop hub in Kolkata", text: "Your parcel is received, checked and packed for the border." },
  { icon: "truck", title: "Delivered to your door", text: "Cleared and delivered anywhere in Bangladesh in 7-12 days." },
];

export function HowItWorks({ compact }: { compact?: boolean }) {
  return (
    <section className={compact ? "" : "mt-10"} aria-labelledby="how">
      {!compact && (
        <div className="mb-4 px-4 sm:px-0">
          <h2 id="how" className="text-lg font-extrabold tracking-tight sm:text-xl">
            How shopping from India works
          </h2>
          <p className="text-sm text-ink-2">One price in BDT. No Indian card, address or customs paperwork needed.</p>
        </div>
      )}
      <ol className="relative grid gap-3 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
        {STEPS.map((s, i) => (
          <li key={s.title} className="card rise relative flex gap-3 p-4" style={{ ["--i" as string]: i }}>
            <span className="chip-icon is-active shrink-0">
              <Icon name={s.icon} size={18} />
            </span>
            <div>
              <p className="text-[11px] font-bold text-accent">STEP {String(i + 1).padStart(2, "0")}</p>
              <h3 className="text-sm font-bold">{s.title}</h3>
              <p className="mt-0.5 text-xs leading-relaxed text-ink-2">{s.text}</p>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
