import type { Metadata } from "next";
import Link from "next/link";
import { HowItWorks } from "@/components/home/HowItWorks";
import { Icon } from "@/components/Icon";
import { PRICING } from "@/lib/pricing";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";

export async function generateMetadata(): Promise<Metadata> {
  return { title: (await getT()).t("hiw.meta") };
}

const FAQ: [MsgKey, MsgKey][] = [
  ["hiw.q1", "hiw.a1"],
  ["hiw.q2", "hiw.a2"],
  ["hiw.q3", "hiw.a3"],
  ["hiw.q4", "hiw.a4"],
  ["hiw.q5", "hiw.a5"],
];

export default async function HowPage() {
  const { t, bdt } = await getT();
  const formatBdt = bdt;
  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-10">
      <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{t("hiw.title")}</h1>
      <p className="mt-2 max-w-2xl text-ink-2">
        {t("hiw.sub")}
      </p>
      <div className="mt-6">
        <HowItWorks compact />
      </div>

      <section id="shipping" className="card mt-8 p-5">
        <h2 className="flex items-center gap-2 text-lg font-extrabold">
          <span className="chip-icon">
            <Icon name="truck" size={18} />
          </span>
{t("hiw.shipping")}
        </h2>
        <ul className="mt-3 space-y-2 text-sm text-ink-2">
          <li>
            {t("hiw.ship1", { base: formatBdt(PRICING.shipping.baseBdt), perKg: formatBdt(PRICING.shipping.perKgBdt), free: formatBdt(PRICING.shipping.freeAboveBdt) })}
          </li>
          <li>{t("hiw.ship2")}</li>
          <li>{t("hiw.ship3")}</li>
        </ul>
      </section>

      <section id="returns" className="mt-8">
        <h2 className="mb-3 text-lg font-extrabold">{t("hiw.questions")}</h2>
        <div className="space-y-2">
          {FAQ.map(([q, a]) => (
            <details key={q} className="card group p-4 [&_summary::-webkit-details-marker]:hidden">
              <summary className="flex min-h-8 cursor-pointer items-center justify-between gap-3 text-sm font-bold">
                {t(q)}
                <Icon name="plus" size={18} className="shrink-0 text-accent transition-transform group-open:rotate-45" />
              </summary>
              <p className="mt-2 text-sm leading-relaxed text-ink-2">{t(a)}</p>
            </details>
          ))}
        </div>
      </section>

      <Link href="/" className="btn btn-primary mt-8">
        {t("hero.start")} <Icon name="arrow" size={18} className="arrow" />
      </Link>
    </div>
  );
}
