"use client";

import Link from "next/link";
import { MARKET_IDS, STORES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";

export function StoreCards() {
  const { enabled } = useStore();
  const list = MARKET_IDS.filter((m) => enabled[m]);
  return (
    <section className="mt-8 px-4 sm:px-0" aria-labelledby="stores">
      <h2 id="stores" className="text-lg font-extrabold tracking-tight sm:text-xl">
        Where do you want to shop today?
      </h2>
      <p className="text-sm text-ink-2">Every product shows the Indian store we buy it from.</p>
      <div className="no-scrollbar snap-x-rail -mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0">
        {list.map((m, i) => {
          const s = STORES[m];
          return (
            <Link
              key={m}
              href={`/store/${m}`}
              data-store={m}
              className="rise group relative w-[78vw] max-w-[320px] shrink-0 overflow-hidden rounded-3xl p-4 text-white sm:w-auto sm:max-w-none shadow-[var(--shadow-1)] transition hover:-translate-y-1 hover:shadow-[var(--shadow-2)]"
              style={{ ["--i" as string]: i, background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
            >
              <span aria-hidden className="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 transition-transform duration-500 group-hover:scale-125" />
              <span className="grid h-11 w-11 place-items-center rounded-2xl bg-white/20">
                <Icon name={s.icon} size={22} stroke={2} />
              </span>
              <h3 className="mt-3 text-lg font-extrabold">{s.name}</h3>
              <p className="text-xs text-white/85">Sourced from {s.source}</p>
              <p className="mt-2 line-clamp-2 text-xs text-white/80">{s.tagline}</p>
              <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold">
                Enter store <Icon name="arrow" size={16} className="transition-transform group-hover:translate-x-1" />
              </span>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
