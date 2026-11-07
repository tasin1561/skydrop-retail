import Link from "next/link";
import { STORES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { Icon } from "../Icon";

export function CategoryChips({ mode }: { mode: StoreMode }) {
  const cats = STORES[mode].categories;
  return (
    <nav aria-label="Categories" className="no-scrollbar mt-6 flex gap-3 overflow-x-auto px-4 pb-1 sm:px-0">
      {cats.map((c, i) => (
        <Link
          key={c.slug}
          href={`/search?q=&store=${mode}&category=${c.slug}`}
          className="rise group flex w-[84px] shrink-0 flex-col items-center gap-2 text-center sm:w-[104px]"
          style={{ ["--i" as string]: i }}
        >
          <span className="grid h-16 w-16 place-items-center rounded-3xl bg-surface text-accent shadow-[var(--shadow-1)] transition duration-300 group-hover:-translate-y-1 group-hover:bg-accent group-hover:text-white group-hover:shadow-[var(--glow)] sm:h-20 sm:w-20">
            <Icon name={c.icon} size={28} stroke={1.6} />
          </span>
          <span className="text-xs font-semibold leading-tight text-ink">{c.label}</span>
        </Link>
      ))}
    </nav>
  );
}
