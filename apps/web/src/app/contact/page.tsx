import type { Metadata } from "next";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { SUPPORT } from "@/lib/support";

export const metadata: Metadata = {
  title: "Contact us",
  description: "Reach Skydrop Retail support on WhatsApp, Messenger, phone or email.",
};

export default function ContactPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-6 sm:px-6">
      <header className="rise">
        <span className="chip-icon h-12 w-12 rounded-2xl">
          <Icon name="phone" size={22} />
        </span>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Contact us</h1>
        <p className="mt-2 text-ink-2">
          Real people in Dhaka, {SUPPORT.hours}. Have your order number (it starts with SR-) ready so we can help faster.
        </p>
      </header>

      <ul className="mt-6 grid gap-3 sm:grid-cols-2">
        {SUPPORT.channels.map((c, i) => {
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
          Placeholder contact details. Set <code>NEXT_PUBLIC_SUPPORT_WHATSAPP</code>, <code>_MESSENGER</code>, <code>_PHONE</code> and{" "}
          <code>_EMAIL</code> to show the real ones.
        </p>
      )}

      <section className="card mt-8 p-5">
        <h2 className="text-lg font-extrabold tracking-tight">Before you message</h2>
        <ul className="mt-3 space-y-2 text-sm text-ink-2">
          <li className="flex gap-2">
            <Icon name="receipt" size={16} className="mt-0.5 shrink-0 text-accent" /> Order status and tracking are on{" "}
            <Link href="/orders" className="font-semibold text-accent">
              your orders
            </Link>
          </li>
          <li className="flex gap-2">
            <Icon name="refresh" size={16} className="mt-0.5 shrink-0 text-accent" /> Damaged or wrong item? Use &quot;Get help&quot; on the order within 3 days
          </li>
          <li className="flex gap-2">
            <Icon name="help" size={16} className="mt-0.5 shrink-0 text-accent" /> Common questions are answered in{" "}
            <Link href="/help" className="font-semibold text-accent">
              Help
            </Link>
          </li>
        </ul>
        <p className="mt-4 text-xs text-ink-3">Office: {SUPPORT.address}</p>
      </section>
    </div>
  );
}
