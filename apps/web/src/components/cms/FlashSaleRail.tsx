"use client";

import "./cms.css";
import { useT } from "@/i18n/client";
import Link from "next/link";
import type { FlashSale } from "@/lib/cms";
import { Icon } from "@/components/Icon";
import { OriginTag, ProductImage } from "@/components/ProductBits";
import { FlashCountdown } from "./FlashCountdown";

// Flash sale rail with a big countdown. Live sales show the flash price; upcoming ones preview it.
export function FlashSaleRail({ sales }: { sales: FlashSale[] }) {
  if (!sales.length) return null;
  return (
    <>
      {sales.map((s) => (
        <FlashBlock key={s.id} s={s} />
      ))}
    </>
  );
}

function FlashBlock({ s }: { s: FlashSale }) {
  const live = s.status === "live";
  const { t, bdt } = useT();
  const id = `fs-${s.id}`;
  return (
    <section aria-labelledby={id} className="mt-8 px-4 sm:px-0">
      <div className="fs-panel p-4 sm:p-5">
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
          <div className="min-w-0">
            <p className={"inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider " + (live ? "bg-bad text-white" : "bg-surface-2 text-ink-2")}>
              <Icon name="bolt" size={13} stroke={2.4} className={live ? "fs-bolt" : ""} /> {live ? t("flash.live") : t("flash.coming")}
            </p>
            <h2 id={id} className="mt-2 text-xl font-extrabold tracking-tight sm:text-2xl">
              {s.title}
            </h2>
            <p className="text-xs text-ink-2 sm:text-sm">
              {t("flash.extra")}{s.maxQty ? t("flash.upTo", { count: s.maxQty }) : ""}
            </p>
          </div>
          <FlashCountdown to={live ? s.endsAt : s.startsAt} mode={live ? "ends" : "starts"} />
        </div>

        <div className="no-scrollbar snap-x-rail -mx-1 mt-4 flex gap-3 overflow-x-auto px-1 pb-1">
          {s.items.map((it, i) => (
            <Link
              key={it.product.id}
              href={`/p/${it.product.id}`}
              prefetch={false}
              data-store={it.product.market}
              className="rise group flex w-[44vw] max-w-[200px] shrink-0 flex-col overflow-hidden rounded-2xl bg-surface shadow-[var(--shadow-1)] transition-transform duration-300 hover:-translate-y-1 sm:w-[184px]"
              style={{ ["--i" as string]: i % 12 }}
            >
              <div className="relative aspect-square bg-white p-3">
                <ProductImage p={it.product} className="h-full w-full transition-transform duration-500 group-hover:scale-[1.04]" />
                {it.extraOffBdt > 0 && (
                  <span className="absolute left-2 top-2 inline-flex items-center gap-0.5 rounded-full bg-bad px-2 py-0.5 text-[11px] font-extrabold text-white shadow">
                    <Icon name="bolt" size={11} stroke={2.6} /> −{bdt(it.extraOffBdt)}
                  </span>
                )}
              </div>
              <div className="flex flex-1 flex-col gap-1 p-3">
                <OriginTag market={it.product.market} compact />
                <h3 className="line-clamp-2 text-[13px] font-medium leading-snug">{it.product.title}</h3>
                <div className="mt-auto pt-1">
                  <p className="flex flex-wrap items-baseline gap-x-1.5">
                    <span className="text-lg font-extrabold tracking-tight">{bdt(it.flashBdt)}</span>
                    <span className="text-xs text-ink-3 line-through">{bdt(it.regularBdt)}</span>
                  </p>
                  <p className="text-[11px] font-bold text-bad">{live ? t("flash.priceNow") : t("flash.priceWhen")}</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
