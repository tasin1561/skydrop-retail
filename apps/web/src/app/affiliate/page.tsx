import type { Metadata } from "next";
import Link from "next/link";
import "@/components/account/account.css";
import "@/components/growth/growth.css";
import { getFeatures, isOn } from "@/lib/features";
import { DEMO_PROGRAM, type ProgramRules } from "@/lib/growth";
import { formatBdt } from "@/lib/pricing";
import { Icon } from "@/components/Icon";
import { NotAvailable } from "@/components/growth/NotAvailable";

export const metadata: Metadata = {
  title: "Affiliate program",
  description: "Share products from Indian stores and earn commission on every order delivered in Bangladesh. Paid to bKash or Nagad.",
};

const CATEGORY_LABELS: Record<string, string> = { electronics: "Electronics", fashion: "Fashion", beauty: "Beauty", grocery: "Grocery", home: "Home" };

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
  const features = await getFeatures();
  if (!isOn(features, "affiliate"))
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10">
        <NotAvailable title="The affiliate program" />
      </div>
    );
  const p = await program();
  const rates = Object.entries(p.rates).sort((a, b) => b[1] - a[1]);
  const top = Math.max(p.defaultRate, ...rates.map(([, r]) => r));
  const steps = [
    { icon: "user", title: "Apply in a minute", body: "Sign in with your phone, pick your code and add your bKash or Nagad number." },
    { icon: "share", title: "Share any product", body: "Every product page has a share button that adds your code. Or build links from your dashboard." },
    { icon: "truck", title: "We deliver", body: `Your friends shop in taka. We track their orders for ${p.cookieDays} days after they click.` },
    { icon: "wallet", title: "Get paid", body: `Commission is approved ${p.holdDays} days after delivery. Cash out from ${formatBdt(p.minPayoutBdt)}.` },
  ];
  const faqs = [
    ["How much do I earn?", `${p.defaultRate}% of the items' price after discounts (shipping is not counted), up to ${top}% in some categories, capped at ${formatBdt(p.maxPerOrderBdt)} per order.`],
    ["Which order counts?", `The last affiliate link a shopper clicked within ${p.cookieDays} days gets the order.`],
    ["Can I buy through my own link?", "No. Orders from your own account or phone number don't earn commission."],
    ["What if the order is cancelled or returned?", "Its commission is reversed. That is why commissions wait for the return window before approval."],
  ];

  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-12">
      <section className="rise relative overflow-hidden rounded-[24px] p-6 text-white shadow-[var(--glow)] sm:p-10" style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}>
        <div aria-hidden className="gr-float absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/10" />
        <div aria-hidden className="gr-float absolute -bottom-16 right-24 h-36 w-36 rounded-full bg-white/10" style={{ animationDelay: "1.5s" }} />
        <div className="relative max-w-xl">
          <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm">AFFILIATE PROGRAM</span>
          <h1 className="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl">Share India&apos;s stores. Earn in taka.</h1>
          <p className="mt-3 text-white/90 sm:text-lg">
            Earn up to {top}% on every order your followers place from amazon.in, Flipkart and BigBasket, delivered to Bangladesh by Skydrop.
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            <Link href="/account/affiliate" className="btn min-h-11 bg-white px-5 font-bold text-slate-900">
              Apply now <Icon name="arrow" size={18} className="arrow" />
            </Link>
            <a href="#how" className="btn min-h-11 border border-white/40 bg-white/10 px-5 font-bold text-white">
              How it works
            </a>
          </div>
        </div>
      </section>

      <section aria-label="Program at a glance" className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          ["spark", `${p.defaultRate}%`, "standard commission"],
          ["clock", `${p.cookieDays} days`, "link tracking"],
          ["wallet", formatBdt(p.minPayoutBdt), "minimum payout"],
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
          How it works
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
            Commission by category
          </h2>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {rates.map(([cat, r]) => (
              <li key={cat} className="flex items-center justify-between rounded-xl bg-surface-2 px-4 py-3 text-sm">
                <span className="font-semibold">{CATEGORY_LABELS[cat] ?? cat.replace(/[-_]/g, " ")}</span>
                <span className="ac-chip font-bold tabular-nums">{r}%</span>
              </li>
            ))}
            <li className="flex items-center justify-between rounded-xl bg-surface-2 px-4 py-3 text-sm">
              <span className="font-semibold">Everything else</span>
              <span className="ac-chip font-bold tabular-nums">{p.defaultRate}%</span>
            </li>
          </ul>
        </section>
      )}

      <section className="mt-8" aria-labelledby="faq-h">
        <h2 id="faq-h" className="text-xl font-extrabold tracking-tight">
          Questions
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
          Start earning <Icon name="arrow" size={18} className="arrow" />
        </Link>
      </div>
    </div>
  );
}
