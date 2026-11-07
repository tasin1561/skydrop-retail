"use client";

import "./categories.css";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState, type KeyboardEvent } from "react";
import { STORES } from "@/lib/markets";
import type { CatNode } from "@/lib/categories";
import { Icon } from "../Icon";
import { useCurrentMode } from "../StoreTheme";
import { catHref, catLabel, useCategoryMenu, useIsBn } from "./useCategoryMenu";

const ROW = 44; // px, one top-level row (also the touch target)
const MAX_LEAVES = 6;

/**
 * Desktop "All categories" mega menu (md and up). Top-level list on the left (menu pattern: arrows, Home/End,
 * Right/Enter into the panel, Left back, Esc closes), children grouped by level 2 with level-3 links on the right,
 * plus a featured tile. Filtered by the current store mode. Opens on click, keyboard, or hover intent.
 */
export function MegaMenu() {
  const mode = useCurrentMode();
  const bn = useIsBn();
  const { tree, warm } = useCategoryMenu(mode);
  const path = usePathname();
  // Open "at" a pathname, so navigating anywhere closes it without an effect.
  const [openAt, setOpenAt] = useState<string | null>(null);
  const open = openAt === path;
  const [active, setActive] = useState(0);
  const wrap = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const list = useRef<HTMLDivElement>(null);
  const panel = useRef<HTMLDivElement>(null);
  const hover = useRef<ReturnType<typeof setTimeout> | null>(null);
  const id = useId();

  const top = tree[Math.min(active, tree.length - 1)];

  const show = useCallback(
    (focusFirst = false) => {
      warm();
      setOpenAt(path);
      if (focusFirst) requestAnimationFrame(() => list.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus());
    },
    [warm, path],
  );
  const close = useCallback((refocus = false) => {
    setOpenAt(null);
    if (refocus) trigger.current?.focus();
  }, []);

  // Close on outside press and Escape anywhere.
  useEffect(() => {
    if (!open) return;
    const down = (e: PointerEvent) => !wrap.current?.contains(e.target as Node) && close();
    const esc = (e: globalThis.KeyboardEvent) => e.key === "Escape" && close(true);
    document.addEventListener("pointerdown", down);
    document.addEventListener("keydown", esc);
    return () => {
      document.removeEventListener("pointerdown", down);
      document.removeEventListener("keydown", esc);
    };
  }, [open, close]);
  useEffect(() => () => void (hover.current && clearTimeout(hover.current)), []);

  const intent = (fn: () => void, ms: number) => {
    if (hover.current) clearTimeout(hover.current);
    hover.current = setTimeout(fn, ms);
  };

  const items = () => [...(list.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];
  const panelLinks = () => [...(panel.current?.querySelectorAll<HTMLElement>("a[data-cm-link]") ?? [])];

  const onListKey = (e: KeyboardEvent<HTMLDivElement>) => {
    const all = items();
    const i = all.indexOf(document.activeElement as HTMLElement);
    const go = (n: number) => {
      const j = (n + all.length) % all.length;
      setActive(j);
      all[j]?.focus();
    };
    if (e.key === "ArrowDown") go(i + 1);
    else if (e.key === "ArrowUp") go(i - 1);
    else if (e.key === "Home") go(0);
    else if (e.key === "End") go(all.length - 1);
    else if (e.key === "ArrowRight") panelLinks()[0]?.focus();
    else if (e.key === "Tab" && !e.shiftKey && panelLinks().length) panelLinks()[0]?.focus();
    else return;
    e.preventDefault();
  };

  const onPanelKey = (e: KeyboardEvent<HTMLDivElement>) => {
    const all = panelLinks();
    const i = all.indexOf(document.activeElement as HTMLElement);
    if (e.key === "ArrowDown") all[Math.min(all.length - 1, i + 1)]?.focus();
    else if (e.key === "ArrowUp") all[Math.max(0, i - 1)]?.focus();
    else if (e.key === "ArrowLeft") items()[active]?.focus();
    else return;
    e.preventDefault();
  };

  const featured: CatNode | undefined = top?.children.find((c) => c.featured) ?? top?.children[0];

  return (
    <div
      ref={wrap}
      className="hidden shrink-0 md:block"
      onMouseLeave={() => open && intent(() => close(), 260)}
      onMouseEnter={() => hover.current && clearTimeout(hover.current)}
    >
      <button
        ref={trigger}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={`${id}-panel`}
        onClick={() => (open ? close() : show())}
        onMouseEnter={() => !open && intent(() => show(), 160)}
        onKeyDown={(e) => {
          if (e.key === "ArrowDown" || ((e.key === "Enter" || e.key === " ") && !open)) {
            e.preventDefault();
            show(true);
          }
        }}
        className="inline-flex h-11 items-center gap-2 rounded-full bg-surface-2 px-4 text-sm font-semibold text-ink transition-transform active:scale-[0.97]"
      >
        <Icon name="grid" size={18} />
        <span className="hidden lg:inline">All categories</span>
        <span className="lg:hidden">Categories</span>
        <Icon name="chevron" size={16} className="cm-chev" />
      </button>

      {open && top && (
        <div id={`${id}-panel`} className="cm-panel absolute inset-x-0 top-full z-50 border-b border-line bg-surface shadow-[var(--shadow-2)]">
          <div className="mx-auto grid max-w-7xl grid-cols-[250px_minmax(0,1fr)] gap-6 px-4 py-4 lg:grid-cols-[270px_minmax(0,1fr)_240px]">
            {/* level 1 */}
            <div ref={list} role="menu" aria-label="Categories" aria-orientation="vertical" onKeyDown={onListKey} className="relative max-h-[70dvh] overflow-y-auto pr-1">
              <span
                aria-hidden
                className="cm-indicator pointer-events-none absolute inset-x-0 top-0 rounded-xl bg-[var(--accent-soft)]"
                style={{ height: ROW, transform: `translateY(${active * ROW}px)` }}
              />
              {tree.map((n, i) => (
                <Link
                  key={n.path}
                  href={catHref(n.path, mode)}
                  role="menuitem"
                  tabIndex={i === active ? 0 : -1}
                  aria-current={i === active ? "true" : undefined}
                  onMouseEnter={() => setActive(i)}
                  onFocus={() => setActive(i)}
                  className={"relative z-10 flex items-center gap-3 rounded-xl px-3 text-sm font-semibold outline-offset-[-2px] " + (i === active ? "text-accent" : "text-ink-2")}
                  style={{ height: ROW }}
                >
                  <Icon name={n.icon} size={18} />
                  <span className="min-w-0 flex-1 truncate">{catLabel(n, bn)}</span>
                  <Icon name="right" size={14} className={i === active ? "opacity-100" : "opacity-0"} />
                </Link>
              ))}
            </div>

            {/* levels 2 and 3 */}
            <div ref={panel} onKeyDown={onPanelKey} key={top.path} className="cm-swap min-w-0">
              <div className="mb-3 flex items-baseline justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-lg font-extrabold tracking-tight">{catLabel(top, bn)}</p>
                  {!bn && top.nameBn && (
                    <p lang="bn" className="text-xs text-ink-3">
                      {top.nameBn}
                    </p>
                  )}
                </div>
                <Link data-cm-link href={catHref(top.path, mode)} className="cm-link inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-semibold text-accent">
                  See all <Icon name="arrow" size={15} />
                </Link>
              </div>
              <div className="columns-2 gap-6 xl:columns-3">
                {top.children.map((l2, i) => (
                  <div key={l2.path} className="cm-col mb-4 break-inside-avoid" style={{ ["--i" as string]: i }}>
                    <Link data-cm-link href={catHref(l2.path, mode)} className="cm-link flex min-h-9 items-center gap-2 text-sm font-bold text-ink">
                      <span className="chip-icon h-7 w-7 rounded-lg">
                        <Icon name={l2.icon} size={15} />
                      </span>
                      {catLabel(l2, bn)}
                    </Link>
                    <ul className="mt-0.5 space-y-0.5 pl-9">
                      {l2.children.slice(0, MAX_LEAVES).map((l3) => (
                        <li key={l3.path}>
                          <Link data-cm-link href={catHref(l3.path, mode)} className="cm-link block py-1 text-[13px] text-ink-2 hover:text-accent">
                            {catLabel(l3, bn)}
                          </Link>
                        </li>
                      ))}
                      {l2.children.length > MAX_LEAVES && (
                        <li>
                          <Link data-cm-link href={catHref(l2.path, mode)} className="cm-link block py-1 text-[13px] font-semibold text-accent">
                            +{l2.children.length - MAX_LEAVES} more
                          </Link>
                        </li>
                      )}
                    </ul>
                  </div>
                ))}
              </div>
            </div>

            {/* featured tile */}
            {featured && (
              <Link
                key={featured.path}
                data-cm-link
                href={catHref(featured.path, mode)}
                className="cm-swap cm-tile relative hidden self-start overflow-hidden rounded-[var(--radius)] bg-gradient-to-br from-[var(--hero-a)] to-[var(--hero-b)] p-5 text-white lg:block"
              >
                <span className="text-[11px] font-bold uppercase tracking-wider opacity-85">Featured</span>
                <span className="mt-2 grid h-14 w-14 place-items-center rounded-2xl bg-white/15">
                  <Icon name={featured.icon} size={30} stroke={1.6} />
                </span>
                <p className="mt-3 text-lg font-extrabold leading-tight">{catLabel(featured, bn)}</p>
                <p className="mt-1 text-xs opacity-90">
                  {mode === "all" ? "From every connected Indian store" : `From ${STORES[mode].source}`}, delivered to Bangladesh.
                </p>
                <span className="mt-4 inline-flex items-center gap-1 text-sm font-bold">
                  Shop now <Icon name="arrow" size={15} />
                </span>
              </Link>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
