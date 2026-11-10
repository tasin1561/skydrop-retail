"use client";

import "./categories.css";
import Link from "next/link";
import { useDeferredValue, useMemo, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import type { CatNode } from "@/lib/categories";
import type { MarketId, StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";
import { CatThumb } from "./CatThumb";
import { catHref, catLabel, filterTree, useIsBn } from "./useCategoryMenu";
import { useT } from "@/i18n/client";
import { StoreName } from "@/components/store/StoreName";

const fold = (s: string) => s.toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "");

/** Keeps nodes whose name (EN/BN) matches, plus every ancestor of a match; a matching node keeps its whole subtree. */
function search(nodes: CatNode[], q: string): CatNode[] {
  if (!q) return nodes;
  const out: CatNode[] = [];
  for (const n of nodes) {
    const hit = fold(n.name).includes(q) || (n.nameBn ?? "").includes(q);
    if (hit) out.push(n);
    else {
      const kids = search(n.children, q);
      if (kids.length) out.push({ ...n, children: kids });
    }
  }
  return out;
}

const countFor = (n: CatNode) => n.count ?? 0;

/** /categories: the full tree with live search, store filter, product counts and store availability chips. */
export function CategoryExplorer({ tree, initialStore = "all" }: { tree: CatNode[]; initialStore?: StoreMode }) {
  const bn = useIsBn();
  const { t, num } = useT();
  const { enabled } = useStore();
  const [store, setStore] = useState<StoreMode>(initialStore);
  const [text, setText] = useState("");
  const q = fold(useDeferredValue(text).trim());
  const shown = useMemo(() => search(filterTree(tree, store), q), [tree, store, q]);
  const stores = STORE_MODES.filter((m) => m === "all" || enabled[m]);

  return (
    <div className="mt-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-center">
        <label className="field flex min-h-11 flex-1 items-center gap-2 px-3">
          <Icon name="search" size={18} className="text-ink-3" />
          <span className="sr-only">{t("cat.find")}</span>
          <input
            type="search"
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder={t("cat.findPlaceholder")}
            className="min-w-0 flex-1 bg-transparent text-base outline-none"
          />
        </label>
        <div role="group" aria-label={t("cat.store")} className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:px-0">
          {stores.map((m) => (
            <button
              key={m}
              type="button"
              aria-pressed={store === m}
              onClick={() => setStore(m)}
              data-store={m}
              className={"sn inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm " + (store === m ? "shadow-[var(--glow)]" : "bg-surface shadow-[var(--shadow-1)]")}
              style={store === m ? { background: "var(--sn-fill)" } : undefined}
            >
              <StoreName mode={m} fill={store === m} name={m === "all" ? t("common.all") : STORES[m].name} />
            </button>
          ))}
        </div>
      </div>

      <p className="mt-3 text-xs text-ink-3" aria-live="polite">
        {q ? t("cat.matching", { count: shown.length }) : t("cat.departments", { count: shown.length })}
      </p>

      {shown.length === 0 ? (
        <div className="card mt-4 p-8 text-center">
          <p className="font-bold">{t("cat.noneCalled", { text })}</p>
          <p className="mt-1 text-sm text-ink-2">{t("cat.noneHint")}</p>
          <div className="mt-4 flex flex-col justify-center gap-2 sm:flex-row">
            <Link href={`/search?q=${encodeURIComponent(text)}&store=${store}`} className="btn btn-primary">
              {t("cat.searchProducts")} <Icon name="arrow" size={18} className="arrow" />
            </Link>
            <Link href={`/request?q=${encodeURIComponent(text)}`} className="btn btn-ghost">
{t("common.requestProduct")}
</Link>
          </div>
        </div>
      ) : (
        <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
          {shown.map((top, i) => (
            <section key={top.path} className="card rise p-4" style={{ ["--i" as string]: Math.min(i, 8) }} aria-labelledby={`cat-${top.path}`}>
              <div className="flex items-start gap-3">
                <Link href={catHref(top.path, store)} tabIndex={-1} aria-hidden className="shrink-0">
                  <CatThumb node={top} className="h-16 w-16 rounded-2xl" iconSize={28} eager={i < 4} />
                </Link>
                <div className="min-w-0 flex-1">
                  <h2 id={`cat-${top.path}`} className="text-base font-extrabold leading-tight">
                    <Link href={catHref(top.path, store)} className="-my-2.5 inline-flex min-h-11 items-center hover:text-accent">
                      {catLabel(top, bn)}
                    </Link>
                  </h2>
                  {(bn ? top.name : top.nameBn) && (
<p lang={bn ? "en" : "bn"} className="text-xs text-ink-3">
                      {bn ? top.name : top.nameBn}
                    </p>
                  )}
                  <StoreChips stores={top.stores} />
                </div>
                <span className="shrink-0 text-xs font-semibold text-ink-3">{t("cat.items", { count: num(countFor(top)) })}</span>
              </div>
              {/* Sub-categories as photo tiles (MoveOn/Myntra style). While searching, the matching deeper levels show as chips. */}
              <ul className="mt-4 grid grid-cols-4 gap-x-2.5 gap-y-3.5 sm:grid-cols-6">
                {top.children.map((l2) => (
                  <li key={l2.path} className={q && l2.children.length ? "col-span-full" : undefined}>
                    <Link href={catHref(l2.path, store)} className={"flex items-center text-center " + (q && l2.children.length ? "gap-3 text-left" : "flex-col gap-1.5")}>
                      <CatThumb node={l2} className={q && l2.children.length ? "h-12 w-12 rounded-xl" : "aspect-square w-full max-w-[84px] rounded-2xl"} iconSize={26} />
                      <span className="line-clamp-2 text-[12px] font-semibold leading-tight text-ink">{catLabel(l2, bn)}</span>
                    </Link>
                    {q && l2.children.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1.5">
                        {l2.children.map((l3) => (
                          <Link
                            key={l3.path}
                            href={catHref(l3.path, store)}
                            className="cm-chip inline-flex min-h-9 items-center gap-1 rounded-full bg-surface-2 px-3 text-xs font-medium text-ink-2"
                          >
                            {catLabel(l3, bn)}
                            {!!countFor(l3) && <span className="text-ink-3">{num(countFor(l3))}</span>}
                          </Link>
                        ))}
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

function StoreChips({ stores, compact }: { stores: MarketId[]; compact?: boolean }) {
  const { t } = useT();
  return (
    <span className={"flex flex-wrap gap-1 " + (compact ? "shrink-0" : "mt-1.5")} aria-label={t("cat.soldBy", { stores: stores.map((s) => STORES[s].name).join(", ") })}>
      {(["amazon", "flipkart", "myntra"] as MarketId[]).map((m) => (
        <span key={m} className="cm-store" data-on={stores.includes(m)} aria-hidden="true" title={STORES[m].source}>
          {compact ? STORES[m].short : STORES[m].name}
        </span>
      ))}
    </span>
  );
}
