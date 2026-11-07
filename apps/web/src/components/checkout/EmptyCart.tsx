import Link from "next/link";
import { Icon } from "@/components/Icon";

// Empty state: a parcel swaying under a parachute, clouds drifting by. CSS only.
export function EmptyCart() {
  return (
    <section className="fade-in mx-auto flex max-w-md flex-col items-center px-4 py-12 text-center sm:py-20">
      <div className="relative h-48 w-56" aria-hidden="true">
        <svg viewBox="0 0 220 190" className="absolute inset-0 h-full w-full overflow-visible">
          <g className="co-cloud" fill="var(--surface-2)">
            <ellipse cx="30" cy="40" rx="22" ry="9" />
            <ellipse cx="44" cy="34" rx="14" ry="10" />
          </g>
          <g className="co-cloud b" fill="var(--surface-2)">
            <ellipse cx="10" cy="100" rx="18" ry="7" />
            <ellipse cx="22" cy="95" rx="11" ry="8" />
          </g>
          <ellipse className="co-empty-shadow" cx="110" cy="180" rx="34" ry="5" fill="var(--ink-3)" opacity=".4" />
          <g className="co-empty-art">
            <path d="M58 70a52 40 0 0 1 104 0z" fill="var(--accent)" />
            <path d="M58 70a52 40 0 0 1 104 0" fill="none" stroke="var(--accent-2)" strokeWidth="3" opacity=".6" />
            <path d="M84 70a26 40 0 0 1 52 0" fill="var(--accent-2)" opacity=".5" />
            <path d="M60 70l38 56M160 70l-38 56M110 70v56" stroke="var(--ink-3)" strokeWidth="1.5" />
            <rect x="90" y="122" width="40" height="34" rx="6" fill="var(--brand-2)" />
            <path d="M90 134h40M110 122v12" stroke="#fff" strokeOpacity=".55" strokeWidth="2" />
          </g>
        </svg>
      </div>
      <h1 className="mt-2 text-2xl font-extrabold tracking-tight">Your cart is waiting for its first parcel</h1>
      <p className="mt-2 text-sm leading-relaxed text-ink-2">
        Add products from amazon.in, flipkart.com or bigbasket.com. We buy them in India and Skydrop drops them at your door in
        Bangladesh, priced in taka.
      </p>
      <Link href="/" className="btn btn-primary mt-6 px-6">
        Start shopping <Icon name="arrow" size={18} className="arrow" />
      </Link>
    </section>
  );
}
