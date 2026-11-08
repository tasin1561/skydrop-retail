"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import { useToast } from "@/components/Toast";
import { STORES } from "@/lib/markets";
import { PRICING } from "@/lib/pricing";
import type { CartItem } from "@/lib/types";
import { useStore } from "@/state/store";
import { computeTotals, useCoupon, type Group, type Totals } from "./cart-math";
import { useCouponTotals } from "./useCouponTotals";
import { CouponField } from "./CouponField";
import { EmptyCart } from "./EmptyCart";
import { QtyStepper } from "./QtyStepper";
import { DeliveryPromise, SummaryLines, TrustRow } from "./Summary";
import { CartPlusNudge } from "@/components/money/CartPlusNudge";
import { useBundleTotals } from "@/components/shopping/useBundleTotals";
import { CartShoppingExtras } from "@/components/shopping/CartShoppingExtras";
import { FreeShipMeter } from "@/components/motion/FreeShipMeter";
import { SavedChip } from "@/components/motion/SavedChip";
import { CountUp } from "@/components/motion/CountUp";
import { useT } from "@/i18n/client";
import { StoreName } from "@/components/store/StoreName";

const key = (i: Pick<CartItem, "productId" | "variant">) => i.productId + "::" + (i.variant ?? "");

type Undo = { item: CartItem; id: number };

export function CartView() {
  const { cart, ready, addToCart, removeFromCart, setQty, toggleWish, wishlist } = useStore();
  const [coupon] = useCoupon();
  const toast = useToast();
  const { t: tr } = useT();
  const [leaving, setLeaving] = useState<string[]>([]);
  const [undo, setUndo] = useState<Undo | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => () => {
    if (timer.current) clearTimeout(timer.current);
  }, []);

  // API mode: coupon discount from the server quote; Shopping: bundle saving
  const t = useBundleTotals(useCouponTotals(computeTotals(cart, coupon), cart, coupon), cart);

  // Let the row slide out and its space close first (about 480ms), then remove it from the store.
  const animateOut = (item: CartItem, after: () => void) => {
    const k = key(item);
    setLeaving((l) => [...l, k]);
    setTimeout(() => {
      after();
      setLeaving((l) => l.filter((x) => x !== k));
    }, 480);
  };

  const remove = (item: CartItem) =>
    animateOut(item, () => {
      removeFromCart(item.productId, item.variant);
      if (timer.current) clearTimeout(timer.current);
      setUndo({ item, id: Date.now() });
      timer.current = setTimeout(() => setUndo(null), 5000);
    });

  const saveForLater = (item: CartItem) =>
    animateOut(item, () => {
      if (!wishlist.includes(item.productId)) toggleWish(item.productId);
      removeFromCart(item.productId, item.variant);
      toast({ kind: "success", title: tr("cart.saved"), body: tr("cart.movedWish"), action: { label: tr("cart.openWish"), href: "/wishlist" } });
    });

  const restore = () => {
    if (!undo) return;
    addToCart(undo.item);
    setUndo(null);
    if (timer.current) clearTimeout(timer.current);
  };

  if (!ready) return <CartSkeleton />;
  if (!cart.length && !undo) return <EmptyCart />;

  return (
    <div className="co-bottom-pad mx-auto max-w-6xl px-4 pt-4 sm:px-6 sm:pt-8">
      <div className="mb-4 flex items-end justify-between gap-3 sm:mb-6">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{tr("cart.title")}</h1>
          <p className="text-sm text-ink-2" aria-live="polite">
            {tr("cart.countLine", { items: tr("cart.items", { count: t.count }), stores: tr("cart.stores", { count: t.groups.length }) })}
          </p>
          {t.discount > 0 && (
            <div className="mt-2">
              <SavedChip amount={t.discount} />
            </div>
          )}
        </div>
        <Link href="/" className="hidden items-center gap-1 text-sm font-semibold text-accent sm:inline-flex">
          <Icon name="left" size={16} /> {tr("cart.keepShopping")}
        </Link>
      </div>

      {!cart.length ? (
        <div className="card fade-in p-6 text-center text-sm text-ink-2">{tr("cart.emptyUndo")}</div>
      ) : (
        <div className="grid gap-6 md:grid-cols-[1fr_340px] lg:grid-cols-[1fr_380px]">
          <div className="space-y-4">
            {t.groups.map((g, gi) => (
              <StoreGroup key={g.market} g={g} gi={gi} leaving={leaving} onRemove={remove} onSave={saveForLater} setQty={setQty} />
            ))}
            <CartShoppingExtras items={cart} />
            {t.groups.length > 1 && (
              <p className="flex items-start gap-2 px-1 text-xs text-ink-2">
                <Icon name="info" size={15} className="mt-px shrink-0" />
                {tr("cart.multiStore")}
              </p>
            )}
            <div className="md:hidden">
              <SideCard t={t} coupon={coupon} />
            </div>
          </div>

          <aside className="hidden md:block">
            <div className="sticky top-24">
              <SideCard t={t} coupon={coupon} withCta />
            </div>
          </aside>
        </div>
      )}

      {/* mobile sticky checkout bar, above the 64px bottom nav */}
      {cart.length > 0 && (
        <div className="co-bottom-bar fixed inset-x-0 bottom-16 z-40 mb-[env(safe-area-inset-bottom)] px-4 pt-3 md:hidden">
          <div className="mx-auto flex max-w-md items-center gap-3">
            <div className="min-w-0 flex-1">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-3">{tr("cart.totalItems", { count: t.count })}</p>
              <p className="text-lg font-extrabold leading-tight tabular-nums">
                <CountUp value={t.total} />
              </p>
            </div>
            <Link href="/checkout" className="btn btn-primary shrink-0 px-5">
{tr("cart.checkout")} <Icon name="arrow" size={18} className="arrow" />
            </Link>
          </div>
        </div>
      )}

      {undo && (
        <div
          key={undo.id}
          role="status"
          aria-live="polite"
          className="toast-in fixed inset-x-4 bottom-[calc(10rem+env(safe-area-inset-bottom))] z-[60] mx-auto flex max-w-sm items-center gap-3 overflow-hidden rounded-2xl bg-ink p-2 pl-3 text-bg shadow-[var(--shadow-2)] md:inset-x-auto md:bottom-6 md:right-6"
        >
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10">
            <Icon name="trash" size={17} />
          </span>
          <p className="min-w-0 flex-1 text-sm">
            <span className="block font-bold">{tr("cart.removed")}</span>
            <span className="line-clamp-1 text-xs opacity-75">{undo.item.title}</span>
          </p>
          <button type="button" onClick={restore} className="h-11 shrink-0 rounded-xl px-4 text-sm font-extrabold text-accent-2 hover:bg-white/10">
{tr("cart.undo")}
</button>
          <span className="toast-bar absolute bottom-0 left-0 h-0.5 w-full bg-accent-2" style={{ animationDuration: "5000ms" }} />
        </div>
      )}
    </div>
  );
}

function SideCard({ t, coupon, withCta }: { t: Totals & { couponError?: string | null }; coupon: ReturnType<typeof useCoupon>[0]; withCta?: boolean }) {
  const { cart } = useStore(); // money module: Plus nudge needs the cart lines
  const { t: tr } = useT();
  return (
    <section className="co-card space-y-4 p-4 sm:p-5" aria-labelledby="sum-h">
      <h2 id="sum-h" className="text-base font-extrabold">
{tr("cart.summary")}
</h2>
      <CouponField error={t.couponError} />
      <SummaryLines t={t} coupon={coupon} perStore />
      <CartPlusNudge cart={cart} coupon={coupon?.code} />
      {withCta && (
        <Link href="/checkout" className="btn btn-primary w-full">
          {tr("cart.proceed")} <Icon name="arrow" size={18} className="arrow" />
        </Link>
      )}
      <DeliveryPromise compact />
      <TrustRow />
    </section>
  );
}

function StoreGroup({
  g,
  gi,
  leaving,
  onRemove,
  onSave,
  setQty,
}: {
  g: Group;
  gi: number;
  leaving: string[];
  onRemove: (i: CartItem) => void;
  onSave: (i: CartItem) => void;
  setQty: (id: string, v: string | undefined, q: number) => void;
}) {
  const s = STORES[g.market];
  const { t: tr, bdt } = useT();
  return (
    <section data-store={g.market} className="co-card rise overflow-hidden" style={{ ["--i" as string]: gi }} aria-label={tr("cart.itemsFrom", { source: s.source })}>
      <header className="co-store-head flex items-center gap-3 p-4 pb-3">
        <div className="min-w-0 flex-1">
          <h2 className="leading-tight">
            <StoreName mode={g.market} size="text-lg" className="tracking-tight" />
          </h2>
          <p className="text-xs text-ink-2">
            {tr("cart.fromSource", { source: s.source })} · {tr("cart.items", { count: g.count })}
          </p>
        </div>
        <span className="shrink-0 text-right text-sm font-bold tabular-nums">{bdt(g.sub)}</span>
      </header>

      <ul>
        {g.items.map((item, i) => {
          const k = key(item);
          return (
            <li key={k} data-leaving={leaving.includes(k)} className="mo-collapse co-row" style={{ animationDelay: `${i * 40}ms` }}>
              <div className="flex gap-3 px-4 py-3">
              <Link href={`/p/${item.productId}`} className="h-20 w-20 shrink-0 overflow-hidden rounded-[10px] img-well bg-surface-2 p-1.5 sm:h-24 sm:w-24" tabIndex={-1} aria-hidden="true">
                <ProductImage p={item} className="h-full w-full rounded-xl" />
              </Link>
              <div className="min-w-0 flex-1">
                <Link href={`/p/${item.productId}`} className="line-clamp-2 text-sm font-semibold leading-snug hover:text-accent">
                  {item.title}
                </Link>
                {item.variant && <p className="mt-0.5 line-clamp-1 text-xs text-ink-2">{item.variant}</p>}
                <p className="mt-1 text-[15px] font-extrabold tabular-nums">
                  {bdt(item.unitBdt * item.qty)}
                  {item.qty > 1 && <span className="ml-1.5 text-xs font-medium text-ink-3">{tr("cart.each", { amount: bdt(item.unitBdt) })}</span>}
                </p>
                <div className="mt-2 flex flex-wrap items-center gap-x-1 gap-y-2">
                  <QtyStepper value={item.qty} label={item.title} onChange={(n) => setQty(item.productId, item.variant, n)} />
                  <button
                    type="button"
                    onClick={() => onSave(item)}
                    className="inline-flex h-11 items-center gap-1.5 rounded-full px-3 text-xs font-semibold text-ink-2 hover:bg-accent/10 hover:text-accent"
                  >
                    <Icon name="heart" size={15} /> {tr("cart.saveLater")}
                  </button>
                  <button
                    type="button"
                    onClick={() => onRemove(item)}
                    className="inline-flex h-11 items-center gap-1.5 rounded-full px-3 text-xs font-semibold text-ink-2 hover:bg-bad/10 hover:text-bad"
                    aria-label={tr("cart.removeItem", { title: item.title })}
                  >
                    <Icon name="trash" size={15} /> {tr("cart.remove")}
                  </button>
                </div>
              </div>
              </div>
            </li>
          );
        })}
      </ul>

      <footer className="space-y-2 bg-surface-2/60 p-4 pt-3">
        <div className="flex items-center justify-between gap-3 text-sm">
          <span className="inline-flex items-center gap-2 text-ink-2">
            <Icon name="truck" size={16} /> {tr("cart.shipping")}
          </span>
          <span className={"font-bold tabular-nums " + (g.shipping === 0 ? "text-good" : "")}>{g.shipping === 0 ? tr("common.free") : bdt(g.shipping)}</span>
        </div>
        <FreeShipMeter sub={g.sub} threshold={PRICING.shipping.freeAboveBdt} source={s.source} />
      </footer>
    </section>
  );
}

function CartSkeleton() {
  const { t: tr } = useT();
  return (
    <div className="mx-auto max-w-6xl px-4 pt-6 sm:px-6" aria-busy="true" aria-label={tr("cart.loading")}>
      <div className="skeleton mb-6 h-8 w-40 rounded-xl" />
      <div className="grid gap-6 md:grid-cols-[1fr_340px]">
        <div className="space-y-4">
          {[0, 1].map((i) => (
            <div key={i} className="skeleton h-48 rounded-[12px]" />
          ))}
        </div>
        <div className="skeleton hidden h-80 rounded-[12px] md:block" />
      </div>
    </div>
  );
}
