import Link from "next/link";
import { storeText } from "@/lib/markets";
import { getT } from "@/i18n/server";
import type { StoreMode } from "@/lib/types";
import { Icon } from "../Icon";

export async function CategoryChips({ mode }: { mode: StoreMode }) {
  const { t } = await getT();
  const cats = storeText(mode, t).categories;
  return (
    <nav aria-label={t("cat.categories")} className="no-scrollbar mt-5 flex gap-2 overflow-x-auto px-4 pb-1 sm:px-0">
      {cats.map((c) => (
        <Link
          key={c.slug}
          href={`/search?q=&store=${mode}&category=${c.slug}`}
          className="group flex w-[80px] shrink-0 flex-col items-center gap-2 text-center sm:w-[96px]"
        >
          <span className="grid h-14 w-14 place-items-center rounded-xl border border-line bg-surface text-ink-2 transition-colors duration-200 group-hover:border-accent group-hover:text-accent sm:h-16 sm:w-16">
            <Icon name={c.icon} size={24} stroke={1.6} />
          </span>
          <span className="text-xs font-semibold leading-tight text-ink">{c.label}</span>
        </Link>
      ))}
    </nav>
  );
}
