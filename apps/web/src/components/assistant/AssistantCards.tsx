"use client";

import Link from "next/link";
import { useState } from "react";
import type { AssistantProduct, CardEvent, DeliveryData, OrderCardData } from "@/lib/assistant/types";
import { STORES } from "@/lib/markets";
import { formatBdt } from "@/lib/pricing";
import { statusText } from "@/lib/orderStatus";
import type { OrderStatus } from "@/lib/types";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";

/** Inline result cards inside an assistant reply. */
export function AssistantCard({ card, onNavigate }: { card: CardEvent; onNavigate: () => void }) {
  switch (card.type) {
    case "products":
      return <ProductRail items={card.data.items} onNavigate={onNavigate} />;
    case "compare":
      return <CompareTable items={card.data.items} rows={card.data.rows} onNavigate={onNavigate} />;
    case "orders":
      return (
        <div className="ai-stack">
          {card.data.items.map((o) => (
            <OrderCard key={o.id} o={o} onNavigate={onNavigate} />
          ))}
        </div>
      );
    case "order":
      return <OrderCard o={card.data} detailed onNavigate={onNavigate} />;
    case "cart":
      return <CartAction p={card.data.product} variant={card.data.variant ?? undefined} qty={card.data.qty} onNavigate={onNavigate} />;
    case "delivery":
      return <DeliveryInfo d={card.data} />;
    case "priceHistory":
      return <PriceSpark points={card.data.points} current={card.data.currentBdt} />;
    case "login":
      return (
        <Link href="/login" onClick={onNavigate} className="ai-card ai-row ai-tap">
          <span className="chip-icon h-9 w-9 shrink-0 rounded-xl">
            <Icon name="user" size={18} />
          </span>
          <span className="min-w-0 flex-1 text-sm">
            <b className="block">Sign in to see your orders</b>
            <span className="text-ink-2">Phone number and one-time code</span>
          </span>
          <Icon name="right" size={18} />
        </Link>
      );
    case "request":
      return (
        <div className="ai-card ai-row text-sm" role="status">
          <span className="chip-icon h-9 w-9 shrink-0 rounded-xl">
            <Icon name="check" size={18} />
          </span>
          <span>
            Request sent: <b>{card.data.title}</b>. Our team will reply by SMS.
          </span>
        </div>
      );
  }
}

function useAdd() {
  const { addToCart } = useStore();
  const toast = useToast();
  return (p: AssistantProduct, qty = 1, variant?: string) => {
    addToCart({
      productId: p.id,
      market: p.market,
      title: p.title,
      image: p.image,
      placeholder: p.placeholder ?? undefined,
      variant,
      unitBdt: p.priceBdt,
      weightKg: p.weightKg,
      qty,
    });
    toast({ kind: "success", title: "Added to cart", body: p.title.slice(0, 80), action: { label: "View cart", href: "/cart" } });
  };
}

function blocked(p: AssistantProduct) {
  return p.restriction?.action === "block" || !p.inStock;
}

function AddButton({ p, qty = 1, variant, wide }: { p: AssistantProduct; qty?: number; variant?: string; wide?: boolean }) {
  const add = useAdd();
  const [done, setDone] = useState(false);
  if (blocked(p))
    return (
      <button type="button" disabled className={"btn btn-ghost ai-add " + (wide ? "w-full" : "")}>
        {p.inStock ? "Can't ship to BD" : "Out of stock"}
      </button>
    );
  return (
    <button
      type="button"
      className={"btn btn-primary ai-add " + (wide ? "w-full" : "")}
      aria-label={`Add ${p.title} to cart`}
      onClick={() => {
        add(p, qty, variant);
        setDone(true);
      }}
    >
      <Icon name={done ? "check" : "cart"} size={15} stroke={2.4} /> {done ? "Added" : "Add to cart"}
    </button>
  );
}

function MiniProduct({ p, onNavigate }: { p: AssistantProduct; onNavigate: () => void }) {
  const off = p.mrpBdt > p.priceBdt ? Math.round(100 - (p.priceBdt / p.mrpBdt) * 100) : 0;
  return (
    <article className="ai-prod" data-store={p.market}>
      <Link href={`/p/${encodeURIComponent(p.id)}`} onClick={onNavigate} className="flex flex-1 flex-col">
        <div className="relative aspect-square bg-white p-2">
          <ProductImage p={{ title: p.title, image: p.image, placeholder: p.placeholder ?? undefined }} className="h-full w-full" />
          {off >= 10 && <span className="absolute left-1.5 top-1.5 rounded-full bg-brand-2 px-1.5 py-0.5 text-[10px] font-bold text-white">-{off}%</span>}
        </div>
        <div className="flex flex-1 flex-col gap-1 p-2.5">
          <span className="text-[10px] font-bold uppercase tracking-wide text-accent">{STORES[p.market]?.source ?? p.market}</span>
          <h4 className="line-clamp-2 text-[12.5px] font-medium leading-snug text-ink">{p.title}</h4>
          <span className="text-[11px] text-ink-2">
            {p.rating ? (
              <>
                <span className="text-amber-500">★</span> {p.rating.toFixed(1)} · {p.reviews.toLocaleString("en-IN")}
              </>
            ) : (
              "New"
            )}
          </span>
          <span className="mt-auto text-[15px] font-extrabold tracking-tight text-ink">{formatBdt(p.priceBdt)}</span>
        </div>
      </Link>
      <div className="px-2.5 pb-2.5">
        <AddButton p={p} wide />
      </div>
    </article>
  );
}

function ProductRail({ items, onNavigate }: { items: AssistantProduct[]; onNavigate: () => void }) {
  if (!items.length) return null;
  return (
    <div className="ai-rail no-scrollbar" role="list" aria-label={`${items.length} products`}>
      {items.map((p, i) => (
        <div role="listitem" key={p.id} className="ai-rail-item" style={{ ["--i" as string]: i }}>
          <MiniProduct p={p} onNavigate={onNavigate} />
        </div>
      ))}
    </div>
  );
}

function CompareTable({ items, rows, onNavigate }: { items: AssistantProduct[]; rows: { label: string; values: string[] }[]; onNavigate: () => void }) {
  return (
    <div className="ai-card overflow-x-auto p-0">
      <table className="ai-compare">
        <caption className="sr-only">Product comparison</caption>
        <thead>
          <tr>
            <th scope="col">
              <span className="sr-only">Feature</span>
            </th>
            {items.map((p) => (
              <th key={p.id} scope="col">
                <Link href={`/p/${encodeURIComponent(p.id)}`} onClick={onNavigate} className="flex flex-col items-center gap-1">
                  <ProductImage p={{ title: p.title, image: p.image, placeholder: p.placeholder ?? undefined }} className="h-14 w-14 rounded-lg bg-white" />
                  <span className="line-clamp-2 text-[11px] font-semibold leading-tight">{p.title}</span>
                </Link>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.label}>
              <th scope="row">{r.label}</th>
              {r.values.map((v, i) => (
                <td key={i}>{v}</td>
              ))}
            </tr>
          ))}
          <tr>
            <th scope="row">
              <span className="sr-only">Add</span>
            </th>
            {items.map((p) => (
              <td key={p.id}>
                <AddButton p={p} wide />
              </td>
            ))}
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function OrderCard({ o, detailed, onNavigate }: { o: OrderCardData; detailed?: boolean; onNavigate: () => void }) {
  const st = o.status as OrderStatus;
  return (
    <Link href={`/orders/${encodeURIComponent(o.id)}`} onClick={onNavigate} className="ai-card ai-tap block">
      <div className="ai-row">
        <span className="chip-icon h-9 w-9 shrink-0 rounded-xl">
          <Icon name={st === "delivered" ? "check" : st === "cancelled" ? "x" : "truck"} size={18} />
        </span>
        <span className="min-w-0 flex-1">
          <b className="block text-sm">
            {o.id} · {statusText(st) ?? o.status}
          </b>
          <span className="text-xs text-ink-2">
            {o.itemCount} item{o.itemCount === 1 ? "" : "s"} · {formatBdt(o.total)}
            {o.expectedBy ? ` · expected by ${new Date(o.expectedBy).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}` : ""}
          </span>
        </span>
        <Icon name="right" size={18} />
      </div>
      {detailed && o.timeline && o.timeline.length > 0 && (
        <ol className="ai-timeline">
          {o.timeline.map((t, i) => (
            <li key={i}>
              <span>{statusText(t.status as OrderStatus) ?? t.status}</span>
              <time dateTime={t.at}>{new Date(t.at).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}</time>
            </li>
          ))}
        </ol>
      )}
    </Link>
  );
}

function CartAction({ p, variant, qty, onNavigate }: { p: AssistantProduct; variant?: string; qty: number; onNavigate: () => void }) {
  return (
    <div className="ai-card ai-row">
      <Link href={`/p/${encodeURIComponent(p.id)}`} onClick={onNavigate} className="shrink-0">
        <ProductImage p={{ title: p.title, image: p.image, placeholder: p.placeholder ?? undefined }} className="h-14 w-14 rounded-xl bg-white p-1" />
      </Link>
      <div className="min-w-0 flex-1">
        <p className="line-clamp-2 text-[13px] font-semibold leading-snug">{p.title}</p>
        <p className="text-xs text-ink-2">
          {formatBdt(p.priceBdt)}
          {variant ? ` · ${variant}` : ""}
          {qty > 1 ? ` · qty ${qty}` : ""}
        </p>
      </div>
      <AddButton p={p} qty={qty} variant={variant} />
    </div>
  );
}

function DeliveryInfo({ d }: { d: DeliveryData }) {
  const fmt = (s: string) => new Date(s).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  const tone = { on_time: "text-good", maybe: "text-warn", too_late: "text-bad" } as const;
  const label = { on_time: "On time if you order by", maybe: "Tight: may arrive late", too_late: "Too late for delivery before" } as const;
  return (
    <div className="ai-card text-sm">
      <div className="ai-row">
        <span className="chip-icon h-9 w-9 shrink-0 rounded-xl">
          <Icon name="truck" size={18} />
        </span>
        <span>
          <b className="block">
            {d.minDays}-{d.maxDays} days to your door
          </b>
          <span className="text-xs text-ink-2">
            Order today: arrives {fmt(d.earliest)} to {fmt(d.latest)}
          </span>
        </span>
      </div>
      {d.restriction && <p className="mt-2 rounded-lg bg-surface-2 px-2.5 py-1.5 text-xs text-bad">{d.restriction.message}</p>}
      {d.cutoffs.length > 0 && (
        <ul className="mt-2 grid gap-1">
          {d.cutoffs.map((c) => (
            <li key={c.name + c.date} className="flex justify-between gap-2 rounded-lg bg-surface-2 px-2.5 py-1.5 text-xs">
              <span>
                <b>{c.name}</b> ({fmt(c.date)})
              </span>
              <span className={"font-semibold " + tone[c.status]}>
                {c.status === "on_time" ? `${label.on_time} ${fmt(c.orderBy)}` : label[c.status]}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function PriceSpark({ points, current }: { points: { date: string; bdt: number }[]; current: number }) {
  if (points.length < 2) return null;
  const vals = points.map((p) => p.bdt);
  const min = Math.min(...vals);
  const max = Math.max(...vals);
  const span = max - min || 1;
  const d = points.map((p, i) => `${i ? "L" : "M"}${((i / (points.length - 1)) * 100).toFixed(1)},${(30 - ((p.bdt - min) / span) * 28 - 1).toFixed(1)}`).join(" ");
  return (
    <figure className="ai-card text-xs">
      <svg viewBox="0 0 100 30" preserveAspectRatio="none" className="h-12 w-full text-accent" role="img" aria-label={`Price ranged from ${formatBdt(min)} to ${formatBdt(max)}`}>
        <path d={d} fill="none" stroke="currentColor" strokeWidth="1.5" vectorEffect="non-scaling-stroke" />
      </svg>
      <figcaption className="mt-1 flex justify-between text-ink-2">
        <span>Low {formatBdt(min)}</span>
        <span className="font-semibold text-ink">Now {formatBdt(current)}</span>
        <span>High {formatBdt(max)}</span>
      </figcaption>
    </figure>
  );
}
