"use client";

import Link from "next/link";
import { useRef, useState } from "react";
import type { AppNotification } from "@/lib/apiClient";
import { Icon } from "@/components/Icon";
import { groupByDay, timeAgo } from "./useNotifications";
import "./notifications.css";
import { useT } from "@/i18n/client";

// Icon chip per notification type: [icon, color]
const CHIP: Record<string, [string, string]> = {
  order_placed: ["receipt", "var(--accent)"],
  payment_received: ["wallet", "var(--good)"],
  payment_failed: ["card", "var(--bad)"],
  payment_pending: ["clock", "#d97706"],
  order_verified: ["shield", "var(--accent)"],
  order_purchased: ["bag", "var(--accent)"],
  order_india_hub: ["package", "#0891b2"],
  order_in_transit: ["plane", "#0891b2"],
  order_dhaka_hub: ["pin", "#0891b2"],
  order_out_for_delivery: ["truck", "#d97706"],
  order_delivered: ["check", "var(--good)"],
  order_cancelled: ["x", "var(--bad)"],
  review_request: ["star", "#eab308"],
  points_expiring: ["tag", "#9333ea"],
  price_drop: ["tag", "var(--good)"],
  back_in_stock: ["bolt", "var(--accent)"],
  cart_reminder_1h: ["cart", "#9333ea"],
  welcome: ["spark", "var(--accent)"],
  broadcast: ["megaphone", "#9333ea"],
  custom: ["info", "var(--ink-2)"],
};

export function chipFor(n: Pick<AppNotification, "type" | "icon">): [string, string] {
  return CHIP[n.type] ?? [n.icon ?? "info", "var(--accent)"];
}

type Props = {
  items: AppNotification[];
  onRead: (id: number) => void;
  onRemove: (id: number) => void;
  onNavigate?: () => void;
  compact?: boolean;
};

export function NotificationList({ items, onRead, onRemove, onNavigate, compact }: Props) {
  const tr = useT();
  if (items.length === 0) return <EmptyState />;
  return (
    <div className="flex flex-col gap-3">
      {groupByDay(items).map((g) => (
        <section key={g.label} aria-label={tr.t(g.label)}>
          <h3 className="px-1 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-ink-3">{tr.t(g.label)}</h3>
          <ul className="flex flex-col gap-1">
            {g.items.map((n, i) => (
              <Row key={n.id} n={n} index={i} compact={compact} onRead={onRead} onRemove={onRemove} onNavigate={onNavigate} />
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}

function Row({ n, index, compact, onRead, onRemove, onNavigate }: { n: AppNotification; index: number; compact?: boolean } & Omit<Props, "items" | "compact">) {
  const tr = useT();
  const [dx, setDx] = useState(0);
  const start = useRef<{ x: number; y: number; id: number } | null>(null);
  const moved = useRef(false);
  const [icon, color] = chipFor(n);
  const SWIPE = 72;

  const onPointerDown = (e: React.PointerEvent) => {
    if (e.pointerType === "mouse") return; // swipe is for touch; mouse users get the buttons
    start.current = { x: e.clientX, y: e.clientY, id: e.pointerId };
    moved.current = false;
  };
  const onPointerMove = (e: React.PointerEvent) => {
    const s = start.current;
    if (!s || s.id !== e.pointerId) return;
    const x = e.clientX - s.x;
    if (!moved.current && Math.abs(e.clientY - s.y) > Math.abs(x)) {
      start.current = null; // vertical scroll wins
      return;
    }
    if (Math.abs(x) > 6) moved.current = true;
    setDx(Math.max(-140, Math.min(0, x)));
  };
  const onPointerUp = () => {
    if (!start.current) return;
    start.current = null;
    if (dx <= -SWIPE * 1.7) onRemove(n.id);
    else if (dx <= -SWIPE) onRead(n.id);
    setDx(0);
  };

  const body = (
    <>
      <span className="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl" style={{ background: `color-mix(in srgb, ${color} 14%, transparent)`, color }}>
        <Icon name={icon} size={19} stroke={2} />
      </span>
      <span className="min-w-0 flex-1">
        <span className={"block text-sm leading-snug " + (n.read ? "font-medium text-ink-2" : "font-bold text-ink")}>{n.title}</span>
        {n.body && <span className={"mt-0.5 block text-[13px] leading-snug text-ink-2 " + (compact ? "line-clamp-2" : "")}>{n.body}</span>}
        <span className="mt-1 block text-[11px] font-medium text-ink-3">{timeAgo(n.createdAt, tr)}</span>
      </span>
      {!n.read && <span aria-label={tr.t("nt.unread")} className="nc-unread-dot mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-accent" />}
    </>
  );

  return (
    <li className="nc-row relative overflow-hidden rounded-2xl" style={{ animationDelay: `${Math.min(index, 8) * 30}ms` }}>
      {/* revealed behind the row while swiping */}
      <div aria-hidden className="absolute inset-0 flex items-center justify-end gap-2 rounded-2xl px-4 text-xs font-bold text-white" style={{ background: dx <= -SWIPE * 1.7 ? "var(--bad)" : "var(--accent)" }}>
        <Icon name={dx <= -SWIPE * 1.7 ? "trash" : "check"} size={16} stroke={2.2} />
        {dx <= -SWIPE * 1.7 ? tr.t("nt.delete") : tr.t("nt.markRead")}
      </div>
      <div
        className={"nc-row-inner group relative flex items-start gap-3 rounded-2xl p-2.5 " + (n.read ? "bg-surface" : "bg-[color-mix(in_srgb,var(--accent)_6%,var(--surface))]") + (dx ? " dragging" : "")}
        style={{ transform: dx ? `translateX(${dx}px)` : undefined }}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      >
        {n.link ? (
          <Link
            href={n.link}
            className="flex min-w-0 flex-1 items-start gap-3 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-accent"
            onClick={(e) => {
              if (moved.current) {
                e.preventDefault();
                return;
              }
              onRead(n.id);
              onNavigate?.();
            }}
          >
            {body}
          </Link>
        ) : (
          <button type="button" className="flex min-w-0 flex-1 items-start gap-3 text-left" onClick={() => onRead(n.id)}>
            {body}
          </button>
        )}
        <div className="flex shrink-0 flex-col gap-1 opacity-100 transition md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100">
          {!n.read && (
            <button type="button" aria-label={tr.t("nt.markAsRead")} title={tr.t("nt.markAsRead")} onClick={() => onRead(n.id)}
              className="grid h-7 w-7 place-items-center rounded-full text-ink-3 transition hover:bg-surface-2 hover:text-ink">
              <Icon name="check" size={15} stroke={2.2} />
            </button>
          )}
          <button type="button" aria-label={tr.t("nt.deleteN")} title={tr.t("nt.delete")} onClick={() => onRemove(n.id)}
            className="grid h-7 w-7 place-items-center rounded-full text-ink-3 transition hover:bg-surface-2 hover:text-bad">
            <Icon name="x" size={15} stroke={2.2} />
          </button>
        </div>
      </div>
    </li>
  );
}

export function EmptyState() {
  const tr = useT();
  return (
    <div className="flex flex-col items-center px-6 py-10 text-center">
      <span className="relative mb-3 grid h-16 w-16 place-items-center rounded-full bg-surface-2 text-ink-3">
        <Icon name="bell" size={28} />
        <span className="absolute -right-0.5 -top-0.5 grid h-6 w-6 place-items-center rounded-full bg-good text-white">
          <Icon name="check" size={13} stroke={2.6} />
        </span>
      </span>
      <p className="font-bold text-ink">{tr.t("nt.caught")}</p>
      <p className="mt-1 max-w-[16rem] text-sm text-ink-2">{tr.t("nt.caughtB")}</p>
    </div>
  );
}
