import { Icon } from "../Icon";

export function PromoStrip() {
  const items = [
    { icon: "tag", title: "SKYDROP10", text: "10% off your first order" },
    { icon: "truck", title: "Free shipping", text: "On orders over ৳15,000 per store" },
    { icon: "shield", title: "Price promise", text: "Verified live before you pay" },
    { icon: "wallet", title: "Pay your way", text: "bKash, Nagad, cards, 50% advance" },
  ];
  return (
    <section className="no-scrollbar mt-6 flex gap-3 overflow-x-auto px-4 sm:grid sm:grid-cols-4 sm:px-0">
      {items.map((it, i) => (
        <div key={it.title} className="card rise flex min-w-[220px] items-center gap-3 p-3 sm:min-w-0" style={{ ["--i" as string]: i }}>
          <span className="chip-icon shrink-0">
            <Icon name={it.icon} size={18} />
          </span>
          <div>
            <p className="text-sm font-bold">{it.title}</p>
            <p className="text-xs text-ink-2">{it.text}</p>
          </div>
        </div>
      ))}
    </section>
  );
}
