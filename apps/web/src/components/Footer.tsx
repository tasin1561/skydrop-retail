import Link from "next/link";
import { Logo } from "./Icon";
import { ConsentLink } from "./tracking/ConsentLink";
import { LangSwitch } from "./LangSwitch";
import { getT } from "@/i18n/server";

/** Structured corporate footer: brand blurb + four link columns, then a quiet bottom bar. */
export async function Footer() {
  const { t, digits } = await getT();
  const cols = [
    { title: t("footer.shop"), links: [[t("footer.allStores"), "/"], ["Amazon", "/store/amazon"], ["Flipkart", "/store/flipkart"], ["BigBasket", "/store/bigbasket"]] },
    { title: t("footer.yourAccount"), links: [[t("nav.orders"), "/orders"], [t("footer.wishlist"), "/wishlist"], [t("footer.addresses"), "/account/addresses"], [t("nav.signIn"), "/login"]] },
    { title: t("footer.help"), links: [[t("footer.helpFaq"), "/help"], [t("footer.contact"), "/contact"], [t("footer.howItWorks"), "/how-it-works"], [t("footer.shippingCustoms"), "/legal/shipping"], [t("footer.track"), "https://track.skydrop.online"]] },
    { title: t("footer.company"), links: [[t("footer.terms"), "/legal/terms"], [t("footer.privacy"), "/legal/privacy"], [t("footer.refunds"), "/legal/refunds"], [t("footer.shipping"), "/legal/shipping"], [t("footer.contactShort"), "/contact"]] },
  ];
  return (
    <footer className="mt-16 border-t border-line bg-surface pb-24 md:pb-0">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-6">
        <div className="sm:col-span-2">
          <Logo className="text-base" />
          <p className="mt-3 max-w-xs text-[13px] leading-relaxed text-ink-2">{t("footer.about")}</p>
          <LangSwitch size="sm" className="-ml-2 mt-3 flex" />
        </div>
        {cols.map((c) => (
          <nav key={c.title} aria-label={c.title}>
            <h3 className="text-[13px] font-semibold text-ink">{c.title}</h3>
            <ul className="mt-2 text-[13px] text-ink-2 md:mt-3 md:space-y-2">
              {c.links.map(([l, h]) => (
                <li key={l + h}>
                  <Link href={h} className="inline-flex min-h-11 items-center transition-colors hover:text-accent hover:underline hover:underline-offset-4 md:min-h-0">
                    {l}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        ))}
      </div>
      <div className="border-t border-line">
        <div className="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs text-ink-3 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
          <div className="space-y-1">
            <p>
              © {digits(new Date().getFullYear())} Skydrop Retail. {t("footer.independent")}
            </p>
            <p>
              {t("footer.disclaimer")} <ConsentLink />
            </p>
          </div>
          <p className="shrink-0 whitespace-nowrap">
            {t("footer.weAccept")}: <span className="text-ink-2">bKash · Nagad · Visa · Mastercard</span>
          </p>
        </div>
      </div>
      {/* Honeypot for scrapers (hidden from people and screen readers; robots.txt disallows /api). Following it blocks the IP. */}
      <a href="/api/catalog-export" rel="nofollow noindex" tabIndex={-1} aria-hidden="true" style={{ position: "absolute", left: "-10000px", width: 1, height: 1, overflow: "hidden" }}>
        Full catalog export (CSV)
      </a>
    </footer>
  );
}
