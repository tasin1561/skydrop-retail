import type { Metadata } from "next";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { localSupport, SUPPORT } from "@/lib/support";
import { getT } from "@/i18n/server";
import { rich } from "@/i18n/rich";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("footer.contact"), description: t("contact.metaDesc") };
}

export default async function ContactPage() {
  const { t } = await getT();
  const local = localSupport(t);
  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-6 sm:px-6">
      <header className="rise">
        <span className="chip-icon h-12 w-12 rounded-2xl">
          <Icon name="phone" size={22} />
        </span>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{t("footer.contact")}</h1>
        <p className="mt-2 text-ink-2">
          {t("contact.sub", { hours: local.hours })}
        </p>
      </header>

      <ul className="mt-6 grid gap-3 sm:grid-cols-2">
        {local.channels.map((c, i) => {
          const inner = (
            <>
              <span className="chip-icon h-11 w-11 shrink-0 rounded-xl">
                <Icon name={c.icon} size={20} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">{c.label}</span>
                <span className="block truncate text-sm text-ink">{c.value}</span>
                <span className="mt-0.5 block text-xs text-ink-2">{c.note}</span>
              </span>
              {c.href && <Icon name="arrow" size={18} className="shrink-0 text-accent" />}
            </>
          );
          return (
            <li key={c.id} className="rise" style={{ ["--i" as string]: i }}>
              {c.href ? (
                <a
                  href={c.href}
                  target={c.href.startsWith("http") ? "_blank" : undefined}
                  rel="noopener noreferrer"
                  className="card flex min-h-20 items-center gap-3 p-4 transition-transform hover:-translate-y-0.5"
                >
                  {inner}
                </a>
              ) : (
                <div className="card flex min-h-20 items-center gap-3 p-4 opacity-80" title="Set NEXT_PUBLIC_SUPPORT_* to enable">
                  {inner}
                </div>
              )}
            </li>
          );
        })}
      </ul>

      {!SUPPORT.configured && (
        <p className="mt-4 rounded-2xl bg-surface-2 px-4 py-3 text-xs text-ink-2" role="note">
          {rich(t("contact.placeholder"), {
  vars: (
    <>
      <code>NEXT_PUBLIC_SUPPORT_WHATSAPP</code>, <code>_MESSENGER</code>, <code>_PHONE</code>, <code>_EMAIL</code>
    </>
  ),
})}
        </p>
      )}

      <section className="card mt-8 p-5">
        <h2 className="text-lg font-extrabold tracking-tight">{t("contact.before")}</h2>
        <ul className="mt-3 space-y-2 text-sm text-ink-2">
          <li className="flex gap-2">
            <Icon name="receipt" size={16} className="mt-0.5 shrink-0 text-accent" />{" "}
<span>
  {rich(t("contact.status"), {
    link: (
      <Link href="/orders" className="font-semibold text-accent">
        {t("contact.yourOrders")}
      </Link>
    ),
  })}
</span>
          </li>
          <li className="flex gap-2">
            <Icon name="refresh" size={16} className="mt-0.5 shrink-0 text-accent" /> {t("contact.damaged")}
          </li>
          <li className="flex gap-2">
            <Icon name="help" size={16} className="mt-0.5 shrink-0 text-accent" />{" "}
<span>
  {rich(t("contact.common"), {
    link: (
      <Link href="/help" className="font-semibold text-accent">
        {t("contact.helpLink")}
      </Link>
    ),
  })}
</span>
          </li>
        </ul>
        <p className="mt-4 text-xs text-ink-3">{t("contact.office", { address: SUPPORT.address })}</p>
      </section>
    </div>
  );
}
