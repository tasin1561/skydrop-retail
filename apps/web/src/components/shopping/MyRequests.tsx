"use client";

import "./shopping.css";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { apiEnabled } from "@/lib/apiClient";
import { formatBdt } from "@/lib/pricing";
import { myRequests } from "@/lib/shopping/client";
import type { ProductRequest, RequestStatus } from "@/lib/shopping/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import type { MsgKey } from "@/i18n/core";

const STEPS: { id: RequestStatus; label: MsgKey }[] = [
  { id: "new", label: "rq.st.new" },
  { id: "sourcing", label: "rq.st.sourcing" },
  { id: "quoted", label: "rq.st.quoted" },
  { id: "ordered", label: "rq.st.ordered" },
];

/** /account/requests: product requests with a status pipeline, the quote and a pay link. */
export function MyRequests() {
  const { user, ready } = useStore();
  const { t, bdt, date, digits } = useT();
  const [items, setItems] = useState<ProductRequest[] | null>(null);
  const [failed, setFailed] = useState(false);
  const signedOut = apiEnabled && ready && !user;

  useEffect(() => {
    if (!ready || signedOut) return;
    let live = true;
    myRequests()
      .then((r) => live && setItems(r))
      .catch(() => live && setFailed(true));
    return () => {
      live = false;
    };
  }, [ready, signedOut]);

  if (signedOut) {
    return (
      <div className="card p-6 text-center">
        <p className="text-sm text-ink-2">{t("rq.signIn")}</p>
        <Link href="/login?next=/account/requests" className="btn btn-primary mt-4 min-h-11">
{t("nav.signIn")}
</Link>
      </div>
    );
  }
  if (failed) return <p className="card p-6 text-center text-sm text-ink-2">{t("rq.loadFailed")}</p>;
  if (!items) return <div className="skeleton h-40 rounded-2xl" aria-hidden />;
  if (!items.length) {
    return (
      <div className="card p-8 text-center">
        <p className="text-sm text-ink-2">{t("rq.none")}</p>
        <Link href="/request" className="btn btn-primary mt-4 min-h-11">
{t("common.requestProduct")}
</Link>
      </div>
    );
  }
  return (
    <ul className="flex flex-col gap-3">
      {items.map((r, i) => {
        const at = STEPS.findIndex((s) => s.id === r.status);
        return (
          <li key={r.id} className="rise card p-4" style={{ ["--i" as string]: i }}>
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="line-clamp-2 text-sm font-bold">{r.description || r.url}</p>
                <p className="text-xs text-ink-2">
                  {t("rq.meta", { id: r.id, date: date(r.createdAt), qty: r.qty })}
                  {r.budgetBdt ? t("rq.budget", { amount: bdt(r.budgetBdt) }) : ""}
                </p>
              </div>
              {r.status === "declined" && <span className="rounded-full bg-surface-2 px-2.5 py-1 text-xs font-bold text-ink-2">{t("rq.notAvail")}</span>}
            </div>
            {r.status !== "declined" && (
              <ol className="mt-3 grid grid-cols-4 gap-1" aria-label={t("rq.status")}>
                {STEPS.map((s, k) => (
                  <li key={s.id} className="sh-step flex flex-col items-center gap-1 text-center" data-done={k <= at} data-current={k === at} aria-current={k === at ? "step" : undefined}>
                    <span className="sh-step__dot grid h-7 w-7 place-items-center rounded-full bg-surface-2 text-ink-3">
                      {k < at ? <Icon name="check" size={13} stroke={3} /> : <span className="text-[11px] font-bold">{digits(k + 1)}</span>}
                    </span>
                    <span className={"text-[11px] font-semibold " + (k <= at ? "text-ink" : "text-ink-3")}>{t(s.label)}</span>
                  </li>
                ))}
              </ol>
            )}
            {r.quoteBdt != null && (
              <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 p-3">
                <div>
                  <p className="text-sm">
                    {rich(t("rq.ourPrice"), { price: <b className="tabular-nums">{bdt(r.quoteBdt)}</b> })}
                  </p>
                  {r.quoteNote && <p className="text-xs text-ink-2">{r.quoteNote}</p>}
                </div>
                {r.payUrl && (
                  <Link href={r.payUrl} className="btn btn-primary min-h-11">
{t("common.payNow")}
                  </Link>
                )}
              </div>
            )}
          </li>
        );
      })}
    </ul>
  );
}
