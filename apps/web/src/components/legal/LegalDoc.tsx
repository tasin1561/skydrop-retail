import "./legal.css";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { TocNav } from "./TocNav";

export type LegalSection = { id: string; title: string; body: React.ReactNode };

const DOCS = [
  { href: "/legal/terms", label: "Terms" },
  { href: "/legal/privacy", label: "Privacy" },
  { href: "/legal/refunds", label: "Refunds" },
  { href: "/legal/shipping", label: "Shipping" },
  { href: "/help", label: "Help" },
  { href: "/contact", label: "Contact" },
];

/** Readable policy page: title, draft notice, table of contents (sticky on desktop), numbered sections. */
export function LegalDoc({
  title,
  intro,
  updated,
  current,
  sections,
  icon = "shield",
}: {
  title: string;
  intro: string;
  updated: string;
  current: string;
  sections: LegalSection[];
  icon?: string;
}) {
  return (
    <div className="mx-auto max-w-6xl px-4 pb-16 pt-6 sm:px-6">
      <nav aria-label="Policies" className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
        {DOCS.map((d) => (
          <Link
            key={d.href}
            href={d.href}
            aria-current={d.href === current ? "page" : undefined}
            className={
              "inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm font-semibold transition-colors " +
              (d.href === current ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-ink-2 shadow-[var(--shadow-1)] hover:text-ink")
            }
          >
            {d.label}
          </Link>
        ))}
      </nav>

      <header className="rise mt-6 max-w-3xl">
        <span className="chip-icon h-12 w-12 rounded-2xl">
          <Icon name={icon} size={22} />
        </span>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight [text-wrap:balance] sm:text-4xl">{title}</h1>
        <p className="mt-2 text-base text-ink-2">{intro}</p>
        <p className="mt-3 text-xs text-ink-3">Last updated {updated}</p>
        <p className="mt-4 flex items-start gap-2 rounded-2xl bg-[color-mix(in_srgb,var(--warn)_12%,transparent)] px-4 py-3 text-sm text-ink" role="note">
          <Icon name="info" size={18} className="mt-0.5 shrink-0 text-warn" />
          <span>
            <strong>Draft — have a lawyer review.</strong> This text is a plain-language draft written for a Bangladesh business. It has not been
            checked by a lawyer yet and may change before launch.
          </span>
        </p>
      </header>

      <div className="mt-8 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-12">
        <TocNav items={sections.map(({ id, title }) => ({ id, title }))} />
        <article className="lg-prose min-w-0">
          {sections.map((s, i) => (
            <section key={s.id} aria-labelledby={s.id}>
              <h2 id={s.id} className={i === 0 ? "!mt-0" : ""}>
                <span className="mr-2 text-ink-3 tabular-nums">{i + 1}.</span>
                {s.title}
              </h2>
              {s.body}
            </section>
          ))}
        </article>
      </div>
    </div>
  );
}
