"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";
import { EmiLine } from "@/components/money/EmiLine";
import { SafeSection } from "@/components/resilience/SafeSection";
import type { Variant } from "@/lib/types";
import type { BuyInfo } from "./types";
import { QtyStepper } from "./QtyStepper";
import { groupVariants, Spinner, VariantGroup } from "./Variants";
import { flyToCart } from "@/components/motion/flyToCart";

type Phase = "idle" | "busy" | "done";

function initialSelection(id: string, variants: Variant[]) {
  const v = variants.find((x) => x.id === id) ?? variants.find((x) => x.selected) ?? variants[0];
  return v ? { ...v.dimensions } : {};
}

function matches(v: Variant, sel: Record<string, string>, except?: string) {
  return Object.entries(sel).every(([k, val]) => k === except || v.dimensions[k] === undefined || v.dimensions[k] === val);
}

export function BuyBox({
  info,
  knownIds,
  unavailableIds,
}: {
  info: BuyInfo;
  knownIds: string[];
  unavailableIds: string[];
}) {
  const router = useRouter();
  const toast = useToast();
  const { t, bdt } = useT();
  const { addToCart } = useStore();
  const [qty, setQty] = useState(1);
  const [sel, setSel] = useState<Record<string, string>>(() => initialSelection(info.id, info.variants));
  const [pending, setPending] = useState<{ key: string; value: string } | null>(null);
  const [, startNav] = useTransition();
  const [add, setAdd] = useState<Phase>("idle");
  const [buy, setBuy] = useState<Phase>("idle");
  const [showBar, setShowBar] = useState(false);
  const actionsRef = useRef<HTMLDivElement>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  const known = useMemo(() => new Set(knownIds), [knownIds]);
  const unavailable = useMemo(() => new Set(unavailableIds), [unavailableIds]);
  const groups = useMemo(() => groupVariants(info.variants), [info.variants]);

  const current = info.variants.find((v) => Object.entries(sel).every(([k, val]) => v.dimensions[k] === val));
  const variantLabel = current?.label ?? (Object.values(sel).length ? Object.values(sel).join(" / ") : undefined);

  useEffect(() => {
    const t = timers.current;
    return () => t.forEach(clearTimeout);
  }, []);

  // Show the sticky mobile bar once the main buttons have scrolled up out of view.
  useEffect(() => {
    const el = actionsRef.current;
    if (!el) return;
    const io = new IntersectionObserver(([e]) => setShowBar(!e.isIntersecting && e.boundingClientRect.bottom < 120), {
      threshold: 0,
      rootMargin: "-64px 0px 0px 0px",
    });
    io.observe(el);
    return () => io.disconnect();
  }, []);

  function later(fn: () => void, ms: number) {
    timers.current.push(setTimeout(fn, ms));
  }

  function pick(key: string, value: string) {
    const next = { ...sel, [key]: value };
    const exact = info.variants.find((v) => matches(v, next));
    // If the exact combination doesn't exist, keep the picked value and take the closest other values.
    const target =
      exact ??
      info.variants
        .filter((v) => v.dimensions[key] === value)
        .sort((a, b) => score(b, next) - score(a, next))[0];
    if (!target) return;
    if (target.id && target.id !== info.id && known.has(target.id)) {
      setSel({ ...target.dimensions });
      setPending({ key, value });
      startNav(() => router.push(`/p/${target.id}`, { scroll: false }));
      return;
    }
    setSel({ ...target.dimensions });
  }

  function isAvailable(key: string, value: string) {
    return info.variants.some((v) => v.dimensions[key] === value && matches(v, sel, key));
  }

  function isUnavailable(key: string, value: string) {
    const list = info.variants.filter((v) => v.dimensions[key] === value);
    return list.length > 0 && list.every((v) => v.id !== null && unavailable.has(v.id));
  }

  function put() {
    addToCart({
      productId: info.id,
      market: info.market,
      title: info.title,
      image: info.image,
      placeholder: info.placeholder,
      variant: variantLabel,
      unitBdt: info.unitBdt,
      weightKg: info.weightKg,
      qty,
    });
  }

  // Storytelling add: idle -> busy while a thumbnail arcs to the cart icon -> the cart really changes as it
  // lands (badge pops, icon bumps) -> label morphs to "Added". Busy shows at least 600ms so it never flickers.
  function onAdd(from: HTMLElement | null) {
    if (add !== "idle" || !info.available) return;
    setAdd("busy");
    const t0 = Date.now();
    let added = false;
    const land = () => {
      if (added) return;
      added = true;
      put();
    };
    const flight = flyToCart(from, info.image, land);
    later(() => {
      land();
      setAdd("done");
      toast({
        kind: "success",
        title: t("buy.added", { count: qty }),
        body: variantLabel ? `${variantLabel} · ${bdt(info.unitBdt * qty)}` : bdt(info.unitBdt * qty),
        action: { label: t("buy.viewCart"), href: "/cart" },
      });
      later(() => setAdd("idle"), 1800);
    }, Math.max(600, flight + 40) - (Date.now() - t0));
  }

  function onBuy() {
    if (buy !== "idle" || !info.available) return;
    setBuy("busy");
    put();
    later(() => {
      setBuy("done");
      router.push("/checkout");
    }, 350);
  }

  return (
    <div className="flex flex-col gap-5">
      {groups.length > 0 && (
        <section aria-label={t("buy.options")} className="flex flex-col gap-4">
          {groups.map((g) => (
            <VariantGroup
              key={g.key}
              group={g}
              selected={sel[g.key]}
              isAvailable={(v) => isAvailable(g.key, v)}
              isUnavailable={(v) => isUnavailable(g.key, v)}
              onPick={(v) => pick(g.key, v)}
              pending={pending?.key === g.key ? pending.value : null}
            />
          ))}
          {!current?.id && info.variants.length > 0 && (
            <p className="flex items-start gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs text-ink-2">
              <Icon name="info" size={15} className="mt-px shrink-0 text-accent" />
              {t("buy.optionNote", { source: info.source })}
            </p>
          )}
        </section>
      )}

      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-ink-2">{t("buy.qty")}</p>
          <p className="text-xs text-ink-3" aria-live="polite">
            {qty > 1 ? t("buy.forQty", { amount: bdt(info.unitBdt * qty), count: qty }) : t("buy.upTo10")}
          </p>
        </div>
        <QtyStepper value={qty} onChange={setQty} />
      </div>
      <SafeSection label={t("buy.emi")} quiet>
        <EmiLine amount={info.unitBdt * qty} />
      </SafeSection>

      <div ref={actionsRef} className="grid grid-cols-2 gap-2.5">
        <AddButton phase={add} onClick={(e) => onAdd(e.currentTarget)} disabled={!info.available} className="h-[52px] text-[15px]" />
        <BuyButton phase={buy} onClick={onBuy} disabled={!info.available} className="h-[52px] text-[15px]" />
      </div>

      {/* sticky mobile action bar, above the 64px bottom nav */}
      <div
        data-show={showBar}
        aria-hidden={!showBar}
        inert={!showBar}
        className="pd-sticky fixed inset-x-0 bottom-16 z-40 px-3 pb-2 md:hidden"
      >
        <div className="flex items-center gap-2 rounded-[12px] bg-surface/95 p-2 pl-3.5 shadow-[0_-4px_24px_rgb(15_23_42/0.12),var(--shadow-2)] backdrop-blur-md">
          <div className="min-w-0 flex-1">
            <p className="text-[17px] font-extrabold leading-tight tracking-tight">{bdt(info.unitBdt)}</p>
            <p className="truncate text-[11px] text-ink-2">
              {info.discount > 0 ? (
                <>
                  <span className="line-through">{bdt(info.mrpBdt)}</span> <span className="font-bold text-good">{t("card.off", { pct: info.discount })}</span>
                </>
              ) : (
                variantLabel ?? t("card.from", { source: info.source })
              )}
            </p>
          </div>
          <AddButton phase={add} onClick={(e) => onAdd(e.currentTarget)} disabled={!info.available} compact className="h-11 px-4 text-sm" />
          <BuyButton phase={buy} onClick={onBuy} disabled={!info.available} compact className="h-11 px-4 text-sm" />
        </div>
      </div>
    </div>
  );
}

function score(v: Variant, sel: Record<string, string>) {
  return Object.entries(sel).reduce((n, [k, val]) => n + (v.dimensions[k] === val ? 1 : 0), 0);
}

function AddButton({
  phase,
  onClick,
  disabled,
  compact,
  className,
}: {
  phase: Phase;
  onClick: (e: React.MouseEvent<HTMLButtonElement>) => void;
  disabled?: boolean;
  compact?: boolean;
  className?: string;
}) {
  const { t } = useT();
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-busy={phase === "busy"}
      aria-label={t("buy.addToCart")}
      className={"btn btn-ghost !bg-[var(--accent-soft)] font-bold !text-accent hover:!bg-[color-mix(in_srgb,var(--accent)_18%,transparent)] " + (className ?? "")}
    >
      <span className="roll" aria-hidden="true">
        <span data-on={phase === "idle"} data-gone={phase !== "idle"} className="inline-flex items-center gap-1.5">
          <Icon name="cart" size={compact ? 17 : 19} /> {compact ? t("buy.addShort") : t("buy.addToCart")}
        </span>
        <span data-on={phase === "busy"} data-gone={phase === "done"} className="inline-flex items-center gap-1.5">
          <Spinner size={16} /> {t("buy.adding")}
        </span>
        <span data-on={phase === "done"} className="inline-flex items-center gap-1.5">
          <span className="draw-check inline-flex">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M5 12l5 5 9-11" />
            </svg>
          </span>
          {t("buy.addedShort")}
        </span>
      </span>
    </button>
  );
}

function BuyButton({
  phase,
  onClick,
  disabled,
  compact,
  className,
}: {
  phase: Phase;
  onClick: () => void;
  disabled?: boolean;
  compact?: boolean;
  className?: string;
}) {
  const { t } = useT();
  return (
    <button type="button" onClick={onClick} disabled={disabled} aria-busy={phase !== "idle"} aria-label={t("buy.buyNow")} className={"btn btn-primary font-bold " + (className ?? "")}>
      <span className="roll" aria-hidden="true">
        <span data-on={phase === "idle"} data-gone={phase !== "idle"} className="inline-flex items-center gap-1.5">
          {compact ? t("buy.buyShort") : t("buy.buyNow")} <Icon name="arrow" size={17} className="arrow" />
        </span>
        <span data-on={phase !== "idle"} className="inline-flex items-center gap-1.5">
          <Spinner size={16} /> {compact ? t("buy.going") : t("buy.toCheckout")}
        </span>
      </span>
    </button>
  );
}
