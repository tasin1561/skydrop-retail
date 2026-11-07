"use client";

import type { Offer, Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { Icon } from "../Icon";
import { Countdown } from "./Countdown";
import { OFFER_META } from "./offerMeta";
import { useT } from "@/i18n/client";

function OfferCard({ o, i }: { o: Offer; i: number }) {
  const meta = OFFER_META[o.type] ?? OFFER_META.other;
  const { t } = useT();
  return (
    <li
      className="relative flex w-[240px] shrink-0 flex-col gap-2 rounded-2xl bg-surface p-3.5 shadow-[var(--shadow-1)] transition-transform duration-200 hover:-translate-y-0.5 sm:w-[260px]"
      style={{ ["--i" as string]: i }}
    >
      <div className="flex items-center gap-2">
        <span className={"chip-icon h-8 w-8 shrink-0 " + (o.appliesToOurPrice ? "is-active" : "")}>
          <Icon name={meta.icon} size={16} />
        </span>
        <p className="min-w-0 flex-1 truncate text-sm font-extrabold">{o.title || t(meta.label)}</p>
        {o.count && o.count > 1 ? <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-2">{t("offer.count", { count: o.count })}</span> : null}
      </div>
      {o.detail && <p className="line-clamp-3 text-xs leading-relaxed text-ink-2">{o.detail}</p>}
      <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
        {o.appliesToOurPrice ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-good/15 px-2 py-0.5 text-[11px] font-bold text-good">
            <Icon name="check" size={12} stroke={3} /> {t("offer.inPrice")}
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-2">
            <Icon name="info" size={12} stroke={2.4} /> {t("offer.onSource")}
          </span>
        )}
        {o.endsAt && (
          <span className="inline-flex items-center gap-1 rounded-full bg-bad/10 px-2 py-0.5 text-[11px] font-bold text-bad">
            <Icon name="clock" size={12} stroke={2.4} /> <Countdown endsAt={o.endsAt} compact />
          </span>
        )}
        {typeof o.claimedPct === "number" && (
          <span className="rounded-full bg-warn/15 px-2 py-0.5 text-[11px] font-bold text-warn">{t("offer.claimed", { pct: Math.round(o.claimedPct) })}</span>
        )}
      </div>
    </li>
  );
}

/** Every offer shown on the Indian store, so shoppers never miss one. */
export function OffersSection({ p }: { p: Product }) {
  const offers = [...(p.offers ?? [])].sort((a, b) => Number(b.appliesToOurPrice) - Number(a.appliesToOurPrice));
  const { t, digits } = useT();
  if (!offers.length) return null;
  const applied = offers.filter((o) => o.appliesToOurPrice).length;
  return (
    <section aria-labelledby="offers-h" className="mt-5">
      <div className="mb-2 flex items-end justify-between gap-2">
        <h2 id="offers-h" className="flex items-center gap-2 text-base font-extrabold">
          <Icon name="tag" size={18} className="text-accent" /> {t("offer.title", { source: STORES[p.market].source })}
          <span className="rounded-full bg-accent px-2 py-0.5 text-[11px] font-bold text-white">{digits(offers.length)}</span>
        </h2>
      </div>
      <ul data-reveal="stagger" suppressHydrationWarning className="no-scrollbar snap-x-rail -mx-4 flex gap-3 overflow-x-auto px-4 pb-2 lg:mx-0 lg:px-0">
        {offers.map((o, i) => (
          <OfferCard key={i} o={o} i={i} />
        ))}
      </ul>
      <p className="mt-1 text-xs text-ink-3">
        {applied > 0 ? t("offer.applied") : ""}
        {t("offer.bankNote", { source: STORES[p.market].source })}
      </p>
    </section>
  );
}
