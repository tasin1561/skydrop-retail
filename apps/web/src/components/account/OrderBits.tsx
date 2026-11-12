"use client";

import type { CartItem, OrderStatus } from "@/lib/types";
import { Icon } from "@/components/Icon";
import { progressOf, statusIcon, statusText } from "@/lib/orderStatus";
import { useT } from "@/i18n/client";
import { fmtDate, type Lang } from "@/i18n/core";
import { sizedImg } from "@/lib/img";

export function StatusChip({ status }: { status: OrderStatus }) {
  const tr = useT();
  const tone = status === "cancelled" ? "bad" : status === "delivered" ? "good" : undefined;
  return (
    <span className="ac-chip" data-tone={tone}>
      <Icon name={statusIcon(status)} size={12} stroke={2.4} />
      {statusText(status, tr)}
    </span>
  );
}

export function ProgressBar({ status, label }: { status: OrderStatus; label?: string }) {
  const tr = useT();
  const p = status === "cancelled" ? 1 : Math.max(0.04, progressOf(status));
  const pct = status === "cancelled" ? 0 : Math.round(progressOf(status) * 100);
  return (
    <div
      data-reveal="fade"
      suppressHydrationWarning
      className="ac-bar"
      data-tone={status === "cancelled" ? "bad" : status === "delivered" ? "good" : undefined}
      role="progressbar"
      aria-label={label ?? tr.t("ob.progress")}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={pct}
      aria-valuetext={status === "cancelled" ? statusText(status, tr) : `${tr.digits(pct)}%, ${statusText(status, tr)}`}
    >
      <span style={{ ["--p" as string]: p, opacity: status === "cancelled" ? 0.35 : 1 }} />
    </div>
  );
}

export function Thumb({ item, size = 44 }: { item: Pick<CartItem, "image" | "title" | "placeholder">; size?: number }) {
  const hue = item.placeholder?.hue ?? 210;
  return (
    <span
      className="grid shrink-0 place-items-center overflow-hidden rounded-xl bg-white"
      style={{ width: size, height: size }}
      title={item.title}
    >
      {item.image ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={sizedImg(item.image, 160)} alt="" loading="lazy" decoding="async" className="h-full w-full object-contain p-1" />
      ) : (
        <span
          className="grid h-full w-full place-items-center"
          style={{ background: `radial-gradient(circle at 30% 25%, hsl(${hue} 90% 92%), hsl(${hue} 70% 82%))`, color: `hsl(${hue} 60% 32%)` }}
        >
          <Icon name={item.placeholder?.icon ?? "package"} size={Math.round(size * 0.5)} stroke={1.5} />
        </span>
      )}
    </span>
  );
}

export function orderDate(iso: string, lang: Lang = "en") {
  const d = new Date(iso);
  return isNaN(d.getTime()) ? "" : fmtDate(d, lang, { day: "numeric", month: "short", year: "numeric" });
}

export function shortId(id: string) {
  // Order ids look like "SR-7KQ2PX"; show them whole, trim only unusually long ids.
  const clean = id.toUpperCase();
  return clean.length <= 12 ? clean : "#" + clean.slice(-8);
}
