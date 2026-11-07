"use client";

import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

/** Table of contents: collapsible card on mobile, sticky list with a travelling marker on desktop (scroll-spy). */
export function TocNav({ items }: { items: { id: string; title: string }[] }) {
  const [active, setActive] = useState(items[0]?.id ?? "");
  const [open, setOpen] = useState(false);
  const { t, digits } = useT();
  const list = useRef<HTMLOListElement>(null);
  const marker = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const els = items.map((i) => document.getElementById(i.id)).filter((e): e is HTMLElement => !!e);
    const io = new IntersectionObserver(
      (entries) => {
        const top = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (top) setActive(top.target.id);
      },
      { rootMargin: "-80px 0px -65% 0px" },
    );
    els.forEach((e) => io.observe(e));
    return () => io.disconnect();
  }, [items]);

  useEffect(() => {
    const a = list.current?.querySelector<HTMLElement>(`a[href="#${CSS.escape(active)}"]`);
    if (a && marker.current) marker.current.style.setProperty("--y", `${a.offsetTop}px`);
  }, [active]);

  return (
    <nav aria-label={t("toc.label")} className="lg:sticky lg:top-24 lg:self-start">
      <button
        type="button"
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
        className="card flex min-h-12 w-full items-center justify-between px-4 text-sm font-bold lg:hidden"
      >
        {t("toc.label")}
        <Icon name="chevron" size={18} className={"transition-transform duration-200 " + (open ? "rotate-180" : "")} />
      </button>
      <div className="acc lg:!block" data-open={open}>
        <div className="acc-body lg:!block">
          <div>
            <p className="hidden px-3 pb-2 text-xs font-bold uppercase tracking-wider text-ink-3 lg:block">{t("toc.label")}</p>
            <ol ref={list} className="lg-toc relative mt-2 flex flex-col lg:mt-0 lg:border-l lg:border-line">
              <span ref={marker} aria-hidden className="lg-toc-marker hidden lg:block" />
              {items.map((it, i) => (
                <li key={it.id}>
                  <a href={`#${it.id}`} aria-current={active === it.id ? "true" : undefined} onClick={() => setOpen(false)}>
                    <span className="mr-2 w-4 text-ink-3 tabular-nums">{digits(i + 1)}</span>
                    {it.title}
                  </a>
                </li>
              ))}
            </ol>
          </div>
        </div>
      </div>
    </nav>
  );
}
