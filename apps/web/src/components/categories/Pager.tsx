import Link from "next/link";
import { Icon } from "../Icon";
import { getT } from "@/i18n/server";

/** Numbered pagination with 44px targets (server component). */
export async function Pager({ page, pages, href }: { page: number; pages: number; href: (p: number) => string }) {
  if (pages <= 1) return null;
  const { t, digits } = await getT();
  const nums = Array.from({ length: pages }, (_, i) => i + 1).filter((n) => Math.abs(n - page) < 2 || n === 1 || n === pages);
  return (
    <nav aria-label={t("pager.label")} className="mt-8 flex flex-wrap items-center justify-center gap-1">
      {page > 1 && (
        <Link href={href(page - 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label={t("pager.prev")}>
          <Icon name="left" />
        </Link>
      )}
      {nums.map((n, i) => (
        <span key={n} className="flex items-center gap-1">
          {i > 0 && n - nums[i - 1] > 1 && <span className="px-1 text-ink-3">…</span>}
          <Link
            href={href(n)}
            aria-current={n === page ? "page" : undefined}
            className={
              "grid h-11 min-w-11 place-items-center rounded-full px-3 text-sm font-bold " +
              (n === page ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-ink-2 shadow-[var(--shadow-1)] hover:text-accent")
            }
          >
            {digits(n)}
</Link>
        </span>
      ))}
      {page < pages && (
        <Link href={href(page + 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label={t("pager.next")}>
          <Icon name="right" />
        </Link>
      )}
    </nav>
  );
}
