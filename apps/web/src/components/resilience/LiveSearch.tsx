"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { STORES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { useJob } from "./useJob";
import { WaitStory } from "./WaitStory";
import { useT } from "@/i18n/client";

/**
 * Live store search in progress. Results we already have stay on screen; this banner shows the live
 * search running and the page refreshes in place when the store answers (new cards fade in).
 */
export function LiveSearch({ jobId, q, store, empty }: { jobId: string; q: string; store: StoreMode; empty: boolean }) {
  const router = useRouter();
  const { t } = useT();
  const [outcome, setOutcome] = useState<"live" | "done" | "failed">("live");
  const { elapsed } = useJob(jobId, {
    maxMs: 60_000,
    onDone: (s) => {
      setOutcome(s === "done" ? "done" : "failed");
      if (s === "done") router.refresh();
    },
  });
  const where = store === "all" ? t("ls.where") : STORES[store].source;

  return (
    <div className="flex flex-col gap-4">
      <div role="status" aria-live="polite" className="card flex items-center gap-3 p-3 sm:p-4">
        <span className="search-radar" aria-hidden="true">
          <i />
        </span>
        <div className="min-w-0 flex-1 text-sm">
          {outcome === "live" && (
            <>
              <p className="font-bold">
                {t("ls.searching", { where, q })}
              </p>
              <p className="text-xs text-ink-2">
                {empty ? t("ls.first") : t("ls.auto")} · {t("lf.secs", { n: elapsed })}
              </p>
            </>
          )}
          {outcome === "done" && <p className="font-bold">{t("ls.added")}</p>}
          {outcome === "failed" && (
            <p className="text-ink-2">
              {t("ls.slow")}{empty ? t("ls.broader") : t("ls.have")}
            </p>
          )}
        </div>
        {outcome === "failed" && (
          <button type="button" className="btn btn-ghost min-h-11 px-3" onClick={() => window.location.reload()}>
            {t("err.retry")}
          </button>
        )}
      </div>
      {empty && outcome === "live" && (
        <>
          <WaitStory />
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4" aria-hidden="true">
            {Array.from({ length: 8 }, (_, i) => (
              <div key={i} className="overflow-hidden rounded-2xl bg-surface shadow-[var(--shadow-1)]">
                <div className="skeleton aspect-square" />
                <div className="flex flex-col gap-2 p-3">
                  <div className="skeleton h-3 w-16 rounded-full" />
                  <div className="skeleton h-4 w-full rounded" />
                  <div className="skeleton h-5 w-20 rounded" />
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
