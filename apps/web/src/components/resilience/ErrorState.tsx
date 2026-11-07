"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

/** Friendly full-section error with retry. Used by error.tsx files and SafeSection. */
export function ErrorState({
  title: titleProp,
  body: bodyProp,
  retry,
  compact,
  digest,
}: {
  title?: string;
  body?: string;
  retry?: () => void;
  compact?: boolean;
  digest?: string;
}) {
  const [busy, setBusy] = useState(false);
  const { t } = useT();
  const title = titleProp ?? t("err.title");
  const body = bodyProp ?? t("err.body");
  // Auto-retry once after a short pause: most failures are a brief API hiccup.
  useEffect(() => {
    if (!retry || compact) return;
    const t = setTimeout(() => retry(), 4000);
    return () => clearTimeout(t);
  }, [retry, compact]);

  if (compact) {
    return (
      <div role="status" className="card flex items-center gap-3 p-4 text-sm text-ink-2">
        <Icon name="info" size={18} />
        <span className="flex-1">{title}</span>
        {retry && (
          <button type="button" className="btn btn-ghost min-h-11 px-3" onClick={() => retry()}>
            {t("err.retry")}
          </button>
        )}
      </div>
    );
  }
  return (
    <section role="alert" className="fade-in mx-auto max-w-md px-4 py-16 text-center">
      <span className="chip-icon mx-auto h-14 w-14 rounded-2xl">
        <Icon name="package" size={26} />
      </span>
      <h1 className="mt-4 text-2xl font-extrabold tracking-tight">{title}</h1>
      <p className="mt-2 text-sm text-ink-2">{body}</p>
      <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        {retry && (
          <button
            type="button"
            className="btn btn-primary"
            aria-busy={busy}
            onClick={() => {
              setBusy(true);
              retry();
            }}
          >
            <Icon name="refresh" size={18} /> {busy ? t("err.trying") : t("common.tryAgain")}
          </button>
        )}
        <Link href="/" className="btn btn-ghost">
          {t("err.home")}
        </Link>
      </div>
      {digest && <p className="mt-4 text-[11px] text-ink-3">{t("err.ref", { digest })}</p>}
    </section>
  );
}
