"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore } from "react";
import { storeText } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { isBot, isStorePicked, markStorePicked } from "@/lib/storePick";
import { getConsent, subscribeConsent } from "@/lib/track/consent";
import { trackCustom } from "@/lib/track";
import { useStore } from "@/state/store";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";
import { Icon } from "../Icon";
import { useUrlMode } from "../StoreTheme";
import { StoreName } from "./StoreName";
import { ModalShell, useClosing } from "./ModalShell";
import { announce, useEnabledModes } from "./StoreSwitch";

// Pages where a "where do you want to shop?" question would get in the way of a task.
const QUIET = /^\/(checkout|login|orders|admin)(\/|$)/;
const AD_PARAMS = ["fbclid", "gclid", "gbraid", "wbraid", "ttclid", "msclkid"];

/**
 * First visit: ask where the shopper wants to shop. Once per browser (localStorage + sr-store-picked cookie, which
 * lets the root layout skip this component entirely). Never for bots, never over the cookie banner, never on
 * checkout/login/orders, and on product pages only once the page has settled (never when arriving from an ad).
 */
export function StorePicker() {
  const path = usePathname();
  const sp = useSearchParams();
  const urlMode = useUrlMode();
  const { ready } = useStore();
  const modes = useEnabledModes();
  const stores = modes.filter((m) => m !== "all");
  const trackingOn = useFeature("tracking");
  const consent = useSyncExternalStore(subscribeConsent, getConsent, () => "ssr" as const);
  const [open, setOpen] = useState(false);
  const fromAd = path.startsWith("/p/") && (AD_PARAMS.some((k) => sp.has(k)) || /cpc|paid|ads?$/i.test(sp.get("utm_medium") ?? ""));

  useEffect(() => {
    if (open || !ready || consent === "ssr") return;
    if (urlMode && urlMode !== "all") return; // the URL already chose (StoreTheme marks it picked)
    if (QUIET.test(path) || fromAd) return; // ask on a later page
    if (stores.length < 2) return;
    if (trackingOn && consent === null) return; // the cookie banner goes first; we come back after the choice
    const product = path.startsWith("/p/");
    let idle = 0;
    const timer = window.setTimeout(
      () => {
        const show = () => {
          if (!isStorePicked() && !isBot()) setOpen(true);
        };
        // product pages: wait for the browser to be idle so the product is fully usable first
        if (product && "requestIdleCallback" in window) idle = window.requestIdleCallback(show, { timeout: 2000 });
        else show();
      },
      product ? 2500 : 500,
    );
    return () => {
      clearTimeout(timer);
      if (idle && "cancelIdleCallback" in window) window.cancelIdleCallback(idle);
    };
  }, [open, ready, consent, urlMode, path, fromAd, stores.length, trackingOn]);

  if (!open) return null;
  return <PickerDialog modes={modes} onDone={() => setOpen(false)} />;
}

function PickerDialog({ modes, onDone }: { modes: StoreMode[]; onDone: () => void }) {
  const { t } = useT();
  const { setMode } = useStore();
  const titleId = useId();
  const leadId = useId();
  const first = useRef<HTMLButtonElement>(null);
  const { closing, close } = useClosing(onDone);
  const stores = modes.filter((m) => m !== "all");

  const choose = useCallback(
    (m: StoreMode, how: "card" | "browse_all" | "dismiss") => {
      markStorePicked();
      setMode(m);
      announce(t("header.nowShopping", { store: storeText(m, t).name }));
      trackCustom("store_selected", { store: m, via: "first_visit_picker", how });
      close();
    },
    [close, setMode, t],
  );

  return (
    <ModalShell
      labelledBy={titleId}
      describedBy={leadId}
      onEscape={() => choose("all", "dismiss")}
      closing={closing}
      initialFocus={first}
      width="sm:max-w-[720px]"
    >
      <div className="overflow-y-auto overscroll-contain">
        <div className="flex items-start gap-3 px-5 pb-1 pt-5 sm:px-7 sm:pt-7">
          <div className="min-w-0 flex-1">
            <h2 id={titleId} className="text-xl font-semibold tracking-[-0.01em] sm:text-2xl">
              {t("picker.title")}
            </h2>
            <p id={leadId} className="mt-1 text-sm text-ink-2">
              {t("picker.lead")}
            </p>
          </div>
          <button
            type="button"
            onClick={() => choose("all", "dismiss")}
            aria-label={t("picker.close")}
            className="-mr-2 -mt-2 grid h-11 w-11 shrink-0 place-items-center rounded-full text-ink-2 hover:bg-surface-2 sm:-mr-4 sm:-mt-4"
          >
            <Icon name="x" size={20} />
          </button>
        </div>

        <ul className={"grid gap-2.5 px-5 pt-4 sm:gap-3 sm:px-7 " + (stores.length >= 3 ? "sm:grid-cols-3" : "sm:grid-cols-2")}>
          {stores.map((m, i) => {
            const s = storeText(m, t);
            return (
              <li key={m}>
                <button
                  ref={i === 0 ? first : undefined}
                  type="button"
                  data-store={m}
                  onClick={() => choose(m, "card")}
                  className="sp-card group relative flex h-full w-full items-center gap-3 overflow-hidden rounded-[12px] border border-line bg-surface p-3.5 text-left sm:flex-col sm:items-start sm:gap-0 sm:p-4"
                >
                  <span aria-hidden className="absolute inset-y-0 left-0 w-[2px] bg-accent sm:inset-x-0 sm:bottom-auto sm:top-0 sm:h-[2px] sm:w-auto" />
                  <span className="min-w-0 flex-1 pl-1 sm:w-full sm:pl-0 sm:pt-1">
                    <StoreName mode={m} variant="hero" source size="text-[20px] sm:text-[22px]" />
                    <span className="mt-2 block text-xs leading-snug text-ink-2">{s.categories.slice(0, 3).map((c) => c.label).join(" · ")}</span>
                  </span>
                  <Icon name="arrow" size={18} className="shrink-0 text-ink-3 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-ink sm:hidden" />
                </button>
              </li>
            );
          })}
        </ul>

        <div className="px-5 pb-5 pt-3 sm:px-7 sm:pb-7 sm:pt-4">
          <button
            type="button"
            data-store="all"
            onClick={() => choose("all", "browse_all")}
            className="sp-card flex min-h-[52px] w-full items-center gap-3 rounded-[12px] border border-line bg-surface px-3.5 py-2.5 text-left"
          >
            <span className="min-w-0 flex-1">
              <StoreName mode="all" name={t("picker.browseAll")} size="text-[15px]" className="block" />
              <span className="block text-xs text-ink-2">{t("picker.browseAllText")}</span>
            </span>
          </button>
          <p className="mt-3 text-center text-xs text-ink-3">{t("picker.switchLater")}</p>
        </div>
      </div>
    </ModalShell>
  );
}
