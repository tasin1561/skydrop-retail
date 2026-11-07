"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { NotificationList } from "./NotificationList";
import { useNotifications } from "./useNotifications";
import "./notifications.css";
import { useT } from "@/i18n/client";

/** Header bell: unread badge; dropdown panel on desktop, the /notifications page on mobile. Hidden when signed out or switched off. */
export function NotificationBell() {
  const n = useNotifications();
  const [open, setOpen] = useState(false);
  const { t, digits } = useT();
  const [ring, setRing] = useState(0);
  const prevUnread = useRef(n.unread);
  const wrap = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const path = usePathname();

  // Ring the bell when new unread items arrive.
  useEffect(() => {
    if (n.unread > prevUnread.current) setRing((r) => r + 1);
    prevUnread.current = n.unread;
  }, [n.unread]);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => setOpen(false), [path]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (!wrap.current?.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  if (!n.enabled) return null;
  const label = n.unread > 0 ? t("nt.unreadAria", { count: n.unread }) : t("nt.title");

  return (
    <div ref={wrap} className="relative">
      <button
        type="button"
        aria-label={label}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => {
          if (window.matchMedia("(min-width: 768px)").matches) {
            setOpen((o) => !o);
            if (!open) n.refresh();
          } else router.push("/notifications");
        }}
        className={"relative grid h-11 w-11 place-items-center rounded-full transition hover:bg-surface-2 hover:text-ink " + (open ? "bg-surface-2 text-ink" : "text-ink-2")}
      >
        <span key={ring} className={ring ? "nc-ring" : undefined}>
          <Icon name="bell" size={20} />
        </span>
        {n.unread > 0 && (
          <span
            key={n.unread}
            className="nc-badge absolute right-1.5 top-1.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-bad px-1 text-[10px] font-bold leading-none text-white ring-2 ring-[var(--bg)]"
          >
            {digits(n.unread > 99 ? "99+" : n.unread)}
          </span>
        )}
      </button>

      {open && (
        <div
          role="dialog"
          aria-label={t("nt.title")}
          className="nc-panel absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[380px] overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-2)] md:block"
        >
          <div className="flex items-center justify-between border-b border-line px-4 py-3">
            <p className="font-bold text-ink">
              {t("nt.title")} {n.unread > 0 && <span className="ml-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">{t("nt.new", { count: n.unread })}</span>}
            </p>
            <button type="button" onClick={n.markAllRead} disabled={n.unread === 0}
              className="rounded-full px-2.5 py-1 text-xs font-semibold text-accent transition hover:bg-accent-soft disabled:opacity-40">
              {t("nt.markAll")}
            </button>
          </div>
          <div className="max-h-[min(70vh,520px)] overflow-y-auto overscroll-contain p-2">
            {!n.loaded ? (
              <div className="flex flex-col gap-2 p-1">
                {[0, 1, 2].map((i) => (
                  <div key={i} className="skeleton h-16 rounded-2xl" />
                ))}
              </div>
            ) : (
              <NotificationList compact items={n.items.slice(0, 15)} onRead={n.markRead} onRemove={n.remove} onNavigate={() => setOpen(false)} />
            )}
          </div>
          <Link href="/notifications" onClick={() => setOpen(false)} className="block border-t border-line px-4 py-2.5 text-center text-sm font-semibold text-accent transition hover:bg-surface-2">
            {t("nt.seeAll")}
          </Link>
        </div>
      )}
    </div>
  );
}
