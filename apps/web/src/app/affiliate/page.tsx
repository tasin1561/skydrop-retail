import type { Metadata } from "next";
import Link from "next/link";
import "@/components/account/account.css";
import "@/components/growth/growth.css";
import { getFeatures, isOn } from "@/lib/features";
import { DEMO_PROGRAM, type ProgramRules } from "@/lib/growth";
import { Icon } from "@/components/Icon";
import { NotAvailable } from "@/components/growth/NotAvailable";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("ap.meta"), description: t("ap.metaDesc") };
}

const CATEGORY_LABELS: Record<string, MsgKey> = { electronics: "store.cat.electronics", fashion: "store.cat.fashion", beauty: "store.cat.beauty", grocery: "store.cat.grocery", home: "store.cat.home" };

async function program(): Promise<ProgramRules> {
  const api = process.env.API_URL?.replace(/\/$/, "");
  if (!api) return DEMO_PROGRAM;
  try {
    const res = await fetch(`${api}/affiliate/program`, { headers: { Accept: "application/json" }, next: { revalidate: 300 } });
    return res.ok ? ((await res.json()) as { program: ProgramRules }).program : DEMO_PROGRAM;
  } catch {
    return DEMO_PROGRAM;
  }
}

export default async function AffiliateLanding() {
  const [features, { t, bdt, digits }] = await Promise.all([getFeatures(), getT()]);
  if (!isOn(features, "affiliate"))
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10">
        <NotAvailable title={t("af2.program")} />
      </div>
    );
  const p = await program();
  const rates = Object.entries(p.rates).sort((a, b) => b[1] - a[1]);
  const top = Math.max(p.defaultRate, ...rates.map(([, r]) => r));
  const steps = [
    { icon: "user", title: t("ap.s1"), body: t("ap.s1b") },
    { icon: "share", title: t("ap.s2"), body: t("ap.s2b") },
    { icon: "truck", title: t("ap.s3"), body: t("ap.s3b", { days: p.cookieDays }) },
    { icon: "wallet", title: t("ap.s4"), body: t("ap.s4b", { days: p.holdDays, min: bdt(p.minPayoutBdt) }) },
  ];
  const faqs = [
    [t("ap.q1"), t("ap.a1", { rate: p.defaultRate, top, cap: bdt(p.maxPerOrderBdt) })],
    [t("ap.q2"), t("ap.a2", { days: p.cookieDays })],
    [t("ap.q3"), t("ap.a3")],
    [t("ap.q4"), t("ap.a4")],
  ];

  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-12">
      <section className="rise relative overflow-hidden rounded-[16px] p-6 text-white shadow-[var(--glow)] sm:p-10" style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}>
        <div aria-hidden className="gr-float absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/10" />
        <div aria-hidden className="gr-float absolute -bottom-16 right-24 h-36 w-36 rounded-full bg-white/10" style={{ animationDelay: "1.5s" }} />
        <div className="relative max-w-xl">
          <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm">{t("ap.kicker")}</span>
          <h1 className="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl">{t("ap.title")}</h1>
          <p className="mt-3 text-white/90 sm:text-lg">
            {t("ap.sub", { top })}
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            <Link href="/account/affiliate" className="btn min-h-11 bg-white px-5 font-bold text-slate-900">
              {t("af2.applyNow")} <Icon name="arrow" size={18} className="arrow" />
            </Link>
            <a href="#how" className="btn min-h-11 border border-white/40 bg-white/10 px-5 font-bold text-white">
              {t("hero.how")}
</a>
          </div>
        </div>
      </section>

      <section aria-label={t("ap.glance")} className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          ["spark", `${digits(p.defaultRate)}%`, t("ap.std")],
          ["clock", t("ap.days", { count: p.cookieDays }), t("ap.tracking")],
          ["wallet", bdt(p.minPayoutBdt), t("ap.minPayout")],
          ["phone", "bKash · Nagad", "payout methods"],
        ].map(([icon, v, l], i) => (
          <div key={l} className="card gr-step-card flex flex-col gap-2 p-4" style={{ ["--i" as string]: i }}>
            <span className="chip-icon">
              <Icon name={icon} size={18} />
            </span>
            <span className="text-xl font-extrabold tracking-tight">{v}</span>
            <span className="text-xs font-semibold text-ink-2">{l}</span>
          </div>
        ))}
      </section>

      <section id="how" className="mt-8 scroll-mt-24" aria-labelledby="how-h">
        <h2 id="how-h" className="text-xl font-extrabold tracking-tight">
          {t("hero.how")}
</h2>
        <ol className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((s, i) => (
            <li key={s.title} className="card gr-step-card p-4" style={{ ["--i" as string]: i }}>
              <div className="flex items-center gap-2">
                <span className="chip-icon is-active">
                  <Icon name={s.icon} size={18} />
                </span>
                <span className="text-xs font-bold text-ink-3">{String(i + 1).padStart(2, "0")}</span>
              </div>
              <h3 className="mt-3 font-bold">{s.title}</h3>
              <p className="mt-1 text-sm text-ink-2">{s.body}</p>
            </li>
          ))}
        </ol>
      </section>

      {rates.length > 0 && (
        <section className="card mt-8 p-5" aria-labelledby="rates-h">
          <h2 id="rates-h" className="text-lg font-extrabold tracking-tight">
            {t("ap.byCat")}
          </h2>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {rates.map(([cat, r]) => (
              <li key={cat} className="flex items-center justify-between rounded-xl bg-surface-2 px-4 py-3 text-sm">
                <span className="font-semibold">{CATEGORY_LABELS[cat] ? t(CATEGORY_LABELS[cat]) : cat.replace(/[-_]/g, " ")}</span>
                <span className="ac-chip font-bold tabular-nums">{digits(r)}%</span>
              </li>
            ))}
            <li className="flex items-center justify-between rounded-xl bg-surface-2 px-4 py-3 text-sm">
              <span className="font-semibold">{t("ap.else")}</span>
              <span className="ac-chip font-bold tabular-nums">{digits(p.defaultRate)}%</span>
            </li>
          </ul>
        </section>
      )}

      <section className="mt-8" aria-labelledby="faq-h">
        <h2 id="faq-h" className="text-xl font-extrabold tracking-tight">
          {t("ap.questions")}
        </h2>
        <div className="mt-3 space-y-2">
          {faqs.map(([q, a]) => (
            <details key={q} className="card group p-4">
              <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-semibold">
                {q}
                <Icon name="chevron" size={18} className="shrink-0 transition-transform group-open:rotate-180" />
              </summary>
              <p className="mt-2 text-sm text-ink-2">{a}</p>
            </details>
          ))}
        </div>
      </section>

      <div className="mt-8 text-center">
        <Link href="/account/affiliate" className="btn btn-primary">
          {t("ap.startEarning")} <Icon name="arrow" size={18} className="arrow" />
        </Link>
      </div>
    </div>
  );
}
