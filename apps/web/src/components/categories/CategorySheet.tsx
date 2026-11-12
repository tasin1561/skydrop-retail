"use client";

import "./categories.css";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { STORES } from "@/lib/markets";
import type { CatNode } from "@/lib/catNode";
import { Icon } from "../Icon";
import { CatThumb } from "./CatThumb";
import { useCurrentMode } from "../StoreTheme";
import { catHref, catLabel, useCategoryMenu, useIsBn } from "./useCategoryMenu";
import { useT } from "@/i18n/client";

/**
 * Mobile "Categories" drill-down: a bottom sheet whose levels slide in from the side, with a back button and
 * tappable breadcrumbs. Filtered by the current store mode. Rendered as an icon button for the header (md:hidden).
 */
export function CategorySheetButton() {
  const [open, setOpen] = useState(false);
  const { t } = useT();
  const btn = useRef<HTMLButtonElement>(null);
  const close = useCallback(() => {
    setOpen(false);
    btn.current?.focus();
  }, []);
  return (
    <>
      <button
        ref={btn}
        type="button"
        aria-label={t("cat.browse")}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => setOpen(true)}
        className="grid h-11 w-11 place-items-center rounded-full text-ink-2 transition-transform active:scale-[0.98] md:hidden"
      >
        <Icon name="grid" size={20} />
      </button>
      {open && (
        <CategorySheet onClose={close} />
      )}
    </>
  );
}

export function CategorySheet({ onClose }: { onClose: () => void }) {
  const mode = useCurrentMode();
  const bn = useIsBn();
  const { t } = useT();
  const { tree, warm } = useCategoryMenu(mode);
  const path = usePathname();
  const [stack, setStack] = useState<string[]>([]); // node paths, root first
  const [dir, setDir] = useState<"fwd" | "back">("fwd");
  const closeBtn = useRef<HTMLButtonElement>(null);
  const firstPath = useRef(path);

  const find = (p: string, nodes: CatNode[] = tree): CatNode | undefined => {
    for (const n of nodes) {
      if (n.path === p) return n;
      if (p.startsWith(n.path + "/")) return find(p, n.children);
    }
  };
  const trail = stack.map((p) => find(p)).filter((n): n is CatNode => !!n);
  const current = trail[trail.length - 1];
  const list = current ? current.children : tree;

  useEffect(() => {
    warm();
    closeBtn.current?.focus();
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const esc = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", esc);
    return () => {
      document.body.style.overflow = prev;
      document.removeEventListener("keydown", esc);
    };
  }, [onClose, warm]);
  useEffect(() => {
    if (path !== firstPath.current) onClose();
  }, [path, onClose]);

  const push = (n: CatNode) => {
    setDir("fwd");
    setStack((s) => [...s, n.path]);
  };
  const popTo = (depth: number) => {
    setDir("back");
    setStack((s) => s.slice(0, depth));
  };

  return (
    <div className="fixed inset-0 z-[90] md:hidden" role="dialog" aria-modal="true" aria-label={t("cat.categories")}>
      <button type="button" aria-label={t("cat.close")} tabIndex={-1} onClick={onClose} className="cm-backdrop absolute inset-0 bg-black/45" />
      <div className="cm-sheet absolute inset-x-0 bottom-0 flex max-h-[92dvh] min-h-[60dvh] flex-col overflow-hidden rounded-t-3xl bg-surface pb-[env(safe-area-inset-bottom)] shadow-[var(--shadow-2)]">
        <div className="flex items-center gap-1 border-b border-line px-2 py-2">
          {trail.length ? (
            <button type="button" onClick={() => popTo(trail.length - 1)} aria-label={t("common.back")} className="grid h-11 w-11 place-items-center rounded-full text-ink-2">
              <Icon name="left" size={20} />
            </button>
          ) : (
            <span className="grid h-11 w-11 place-items-center text-accent">
              <Icon name="grid" size={20} />
            </span>
          )}
          <h2 className="min-w-0 flex-1 truncate text-base font-extrabold">{current ? catLabel(current, bn) : t("cat.shopBy")}</h2>
          <button ref={closeBtn} type="button" onClick={onClose} aria-label={t("common.close")} className="grid h-11 w-11 place-items-center rounded-full text-ink-2">
            <Icon name="x" size={20} />
          </button>
        </div>

        <nav aria-label={t("cat.path")} className="no-scrollbar flex shrink-0 items-center gap-1 overflow-x-auto px-3 py-2 text-xs font-semibold">
          <button type="button" onClick={() => popTo(0)} className={"min-h-8 shrink-0 rounded-full px-3 " + (trail.length ? "bg-surface-2 text-ink-2" : "bg-[var(--accent-soft)] text-accent")}>
            {mode === "all" ? t("search.allStores") : STORES[mode].name}
          </button>
          {trail.map((n, i) => (
            <span key={n.path} className="flex shrink-0 items-center gap-1">
              <Icon name="right" size={12} className="text-ink-3" />
              <button
                type="button"
                onClick={() => popTo(i + 1)}
                aria-current={i === trail.length - 1 ? "location" : undefined}
                className={"min-h-8 rounded-full px-3 " + (i === trail.length - 1 ? "bg-[var(--accent-soft)] text-accent" : "bg-surface-2 text-ink-2")}
              >
                {catLabel(n, bn)}
              </button>
            </span>
          ))}
        </nav>

        <ul key={current?.path ?? "root"} className={"min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 pb-4 " + (dir === "fwd" ? "cm-level-fwd" : "cm-level-back")}>
          {current && (
            <li>
              <Link href={catHref(current.path, mode)} onClick={onClose} className="cm-row flex min-h-[52px] items-center gap-3 rounded-2xl px-3 font-bold text-accent">
                <CatThumb node={current} className="h-11 w-11 rounded-xl" iconSize={20} />
                <span className="flex-1">{t("cat.shopAll", { name: catLabel(current, bn) })}</span>
                <Icon name="arrow" size={16} />
              </Link>
            </li>
          )}
          {!tree.length &&
            Array.from({ length: 8 }, (_, i) => (
              <li key={i} aria-hidden className="flex min-h-[52px] items-center gap-3 px-3">
                <span className="skeleton h-11 w-11 rounded-xl" />
                <span className="skeleton h-4 rounded-md" style={{ width: `${46 + ((i * 17) % 34)}%` }} />
              </li>
            ))}
          {list.map((n) => (
            <li key={n.path}>
              {n.children.length ? (
                <button type="button" onClick={() => push(n)} className="cm-row flex min-h-[52px] w-full items-center gap-3 rounded-2xl px-3 text-left">
                  <CatThumb node={n} className="h-11 w-11 rounded-xl" iconSize={20} />
                  <Name n={n} bn={bn} />
                  <Icon name="right" size={16} className="text-ink-3" />
                </button>
              ) : (
                <Link href={catHref(n.path, mode)} onClick={onClose} className="cm-row flex min-h-[52px] items-center gap-3 rounded-2xl px-3">
                  <CatThumb node={n} className="h-11 w-11 rounded-xl" iconSize={20} />
                  <Name n={n} bn={bn} />
                </Link>
              )}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function Name({ n, bn }: { n: CatNode; bn: boolean }) {
  return (
    <span className="min-w-0 flex-1">
      <span className="block truncate text-[15px] font-semibold">{catLabel(n, bn)}</span>
      {(bn ? n.name : n.nameBn) && (
<span lang={bn ? "en" : "bn"} className="block truncate text-xs text-ink-3">
          {bn ? n.name : n.nameBn}
        </span>
      )}
    </span>
  );
}
