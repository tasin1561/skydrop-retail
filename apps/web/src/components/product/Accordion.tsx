"use client";

import { useId, useState } from "react";
import { Icon } from "@/components/Icon";

// Accordion built on the shared .acc / .acc-body / .acc-chev helpers (grid-template-rows).
// `lazy` keeps the body unmounted until it is opened once (used for heavy image blocks).
export function Accordion({
  title,
  icon,
  meta,
  defaultOpen = false,
  lazy = false,
  className,
  children,
}: {
  title: React.ReactNode;
  icon?: string;
  meta?: React.ReactNode;
  defaultOpen?: boolean;
  lazy?: boolean;
  className?: string;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen);
  const [seen, setSeen] = useState(defaultOpen);
  const id = useId();
  return (
    <div className={"acc " + (className ?? "")} data-open={open}>
      <button
        type="button"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => {
          setOpen((o) => !o);
          setSeen(true);
        }}
        className="group flex min-h-[52px] w-full items-center gap-3 rounded-2xl px-1 py-2 text-left transition-colors hover:text-accent"
      >
        {icon && (
          <span className="chip-icon shrink-0 transition-transform duration-200 group-active:scale-[0.98]">
            <Icon name={icon} size={18} />
          </span>
        )}
        <span className="min-w-0 flex-1">
          <span className="block text-[15px] font-bold leading-tight">{title}</span>
          {meta && <span className="block text-xs font-normal text-ink-2">{meta}</span>}
        </span>
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-2 transition-colors group-hover:bg-[var(--accent-soft)] group-hover:text-accent">
          <Icon name="chevron" size={18} className="acc-chev" />
        </span>
      </button>
      <div className="acc-body" id={id} role="region" aria-hidden={!open}>
        <div>
          <div className="pb-2 pt-1" inert={!open}>
            {lazy && !seen ? null : children}
          </div>
        </div>
      </div>
    </div>
  );
}
