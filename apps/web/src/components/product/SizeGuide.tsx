"use client";

import { useId, useRef, useState } from "react";
import { useT } from "@/i18n/client";
import { SIZE_CHARTS as CHARTS, sizeValue, type SizeChartId } from "@/lib/sizeGuide";
import { Icon } from "../Icon";
import { ModalShell, useClosing } from "../store/ModalShell";

/** "Size guide" link beside the size options (as on Myntra and Flipkart): a bottom sheet with size charts in inches or cm. */
export function SizeGuide({ initial = "women" }: { initial?: SizeChartId }) {
  const { t, digits } = useT();
  const [open, setOpen] = useState(false);
  const [tab, setTab] = useState<string>(initial);
  const [cm, setCm] = useState(false);
  const { closing, close } = useClosing(() => setOpen(false));
  const titleId = useId();
  const closeBtn = useRef<HTMLButtonElement>(null);
  const chart = CHARTS.find((c) => c.id === tab) ?? CHARTS[0];
  const show = (v: string | number, body: boolean) => digits(sizeValue(v, body, cm));

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-haspopup="dialog"
        className="-my-2 ml-auto inline-flex min-h-10 items-center gap-1.5 px-1 text-sm font-semibold text-accent"
      >
        <Icon name="ruler" size={15} /> {t("sg.open")}
      </button>
      {open && (
        <ModalShell labelledBy={titleId} onEscape={close} onBackdrop={close} closing={closing} initialFocus={closeBtn}>
          <div className="flex items-center justify-between gap-3">
            <h2 id={titleId} className="text-lg font-extrabold tracking-tight">
              {t("sg.title")}
            </h2>
            <button ref={closeBtn} type="button" onClick={close} aria-label={t("sg.close")} className="-mr-2 grid h-11 w-11 place-items-center rounded-full text-ink-2 hover:bg-surface-2">
              <Icon name="x" size={20} />
            </button>
          </div>
          <div role="tablist" aria-label={t("sg.title")} className="mt-3 flex gap-2 overflow-x-auto no-scrollbar">
            {CHARTS.map((c) => (
              <button
                key={c.id}
                type="button"
                role="tab"
                aria-selected={tab === c.id}
                onClick={() => setTab(c.id)}
                className={"min-h-10 shrink-0 rounded-full px-3.5 text-sm font-semibold ring-1 " + (tab === c.id ? "bg-accent text-white ring-accent" : "bg-surface text-ink ring-line")}
              >
                {t(c.label)}
              </button>
            ))}
          </div>
          <div className="mt-3 flex items-center justify-end gap-1 text-xs font-semibold" role="group" aria-label={t("sg.units")}>
            {([false, true] as const).map((v) => (
              <button
                key={String(v)}
                type="button"
                aria-pressed={cm === v}
                onClick={() => setCm(v)}
                className={"min-h-9 rounded-full px-3 " + (cm === v ? "bg-ink text-bg" : "bg-surface-2 text-ink-2")}
              >
                {t(v ? "sg.cm" : "sg.in")}
              </button>
            ))}
          </div>
          <div className="mt-2 overflow-hidden rounded-xl ring-1 ring-line" role="tabpanel">
            <table className="w-full text-center text-sm tabular-nums">
              <thead className="bg-surface-2 text-xs font-bold text-ink-2">
                <tr>
                  {chart.cols.map((c) => (
                    <th key={c} scope="col" className="px-2 py-2.5">
                      {t(c)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {chart.rows.map((r) => (
                  <tr key={String(r[0])}>
                    {r.map((v, i) =>
                      i === 0 ? (
                        <th key={i} scope="row" className="px-2 py-2.5 font-bold">
                          {show(v, chart.body[i])}
                        </th>
                      ) : (
                        <td key={i} className="px-2 py-2.5">
                          {show(v, chart.body[i])}
                        </td>
                      ),
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <ul className="mt-3 space-y-1.5 text-xs leading-relaxed text-ink-2">
            <li className="flex gap-2">
              <Icon name="info" size={14} className="mt-0.5 shrink-0 text-accent" /> {t("sg.tipBetween")}
            </li>
            <li className="flex gap-2">
              <Icon name="info" size={14} className="mt-0.5 shrink-0 text-accent" /> {t("sg.tipBrand")}
            </li>
          </ul>
        </ModalShell>
      )}
    </>
  );
}
