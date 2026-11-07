"use client";

import Link from "next/link";
import { Icon } from "@/components/Icon";
import "./account.css";
import { useT } from "@/i18n/client";

/** Friendly prompt shown instead of a hard redirect, so the back button keeps working. */
export function SignedOut({ next, title, body }: { next: string; title: string; body: string }) {
  const { t } = useT();
  return (
    <div className="card rise mx-auto flex max-w-md flex-col items-center px-6 py-10 text-center shadow-[var(--shadow-2)]">
      <span className="chip-icon is-active h-14 w-14 rounded-2xl shadow-[var(--glow)]">
        <Icon name="user" size={26} />
      </span>
      <h1 className="mt-4 text-xl font-extrabold tracking-tight">{title}</h1>
      <p className="mt-1 text-sm text-ink-2">{body}</p>
      <Link href={`/login?next=${encodeURIComponent(next)}`} className="btn btn-primary mt-5 w-full sm:w-auto">
        {t("so.signInPhone")} <Icon name="arrow" size={18} className="arrow" />
      </Link>
      <Link href="/" className="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-ink-2 hover:text-accent">
        {t("cart.keepShopping")}
      </Link>
    </div>
  );
}
