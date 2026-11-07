import type { Metadata } from "next";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { FaqSearch, type Faq } from "@/components/legal/FaqSearch";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";

const FAQ_TOPICS = ["ordering", "ordering", "ordering", "ordering", "payment", "payment", "payment", "delivery", "delivery", "delivery", "delivery", "refunds", "refunds", "refunds", "points", "account", "account"];

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return {
    title: t("help.meta"),
    description: t("help.metaDesc"),
  };
}

export default async function HelpPage() {
  const { t } = await getT();
  const FAQS: Faq[] = FAQ_TOPICS.map((topic, i) => ({
    topic: t(`help.topic.${topic}` as MsgKey),
    q: t(`help.q${i + 1}` as MsgKey),
    a: t(`help.a${i + 1}` as MsgKey),
  }));
  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-6 sm:px-6">
      <header className="rise">
        <span className="chip-icon h-12 w-12 rounded-2xl">
          <Icon name="help" size={22} />
        </span>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{t("help.title")}</h1>
        <p className="mt-2 text-ink-2">{t("help.sub")}</p>
      </header>
      <div className="mt-6">
        <FaqSearch faqs={FAQS} />
      </div>
      <div className="mt-10 grid gap-3 sm:grid-cols-2">
        {[
          { href: "/contact", icon: "phone", t: t("help.still"), d: t("help.stillD") },
          { href: "/legal/shipping", icon: "truck", t: t("footer.shippingCustoms"), d: t("help.shipD") },
          { href: "/legal/refunds", icon: "refresh", t: t("help.refunds"), d: t("help.refundsD") },
          { href: "/orders", icon: "receipt", t: t("help.orders"), d: t("help.ordersD") },
        ].map((x) => (
          <Link key={x.href} href={x.href} className="card group flex min-h-16 items-center gap-3 p-4 transition-transform hover:-translate-y-0.5">
            <span className="chip-icon shrink-0">
              <Icon name={x.icon} size={18} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-bold">{x.t}</span>
              <span className="block text-xs text-ink-2">{x.d}</span>
            </span>
            <Icon name="right" size={16} className="text-ink-3 transition-transform group-hover:translate-x-0.5" />
          </Link>
        ))}
      </div>
    </div>
  );
}
