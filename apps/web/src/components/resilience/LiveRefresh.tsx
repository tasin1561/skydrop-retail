"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { useJob } from "./useJob";
import { useT } from "@/i18n/client";

/**
 * The product is shown from our database immediately; this chip tells the shopper the live price is
 * being re-checked and refreshes the page in place when it lands. Silent if it fails (checkout re-verifies).
 */
export function LiveRefresh({ jobId, source }: { jobId: string; source: string }) {
  const router = useRouter();
  const [hidden, setHidden] = useState(false);
  const { t } = useT();
  const { state } = useJob(jobId, {
    maxMs: 60_000,
    onDone: (s) => {
      if (s === "done") router.refresh();
      setTimeout(() => setHidden(true), s === "done" ? 1600 : 0);
    },
  });
  if (hidden) return null;
  return (
    <div
      role="status"
      aria-live="polite"
      className="fade-in fixed bottom-24 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full bg-ink/90 px-4 py-2 text-xs font-semibold text-white shadow-lg md:bottom-6"
    >
      <span className="live-dot" aria-hidden="true" />
      {state === "done" ? t("lr.updated") : t("lr.checking", { source })}
    </div>
  );
}

/** Banner when we are serving a saved copy because the API/store is slow. */
export function StaleNote({ what = "prices" }: { what?: "prices" | "results" }) {
  const { t } = useT();
  return (
    <p role="note" className="mb-3 rounded-xl bg-surface-2 px-3 py-2 text-xs text-ink-2">
      {t("lr.stale", { what: t(what === "results" ? "lr.results" : "lr.prices") })}
    </p>
  );
}
