import Link from "next/link";
import { Icon, Logo } from "./Icon";
import { ConsentLink } from "./tracking/ConsentLink";
import { LangSwitch } from "./LangSwitch";
import { getT } from "@/i18n/server";

export async function Footer() {
  const { t, digits } = await getT();
  const cols = [
    { title: t("footer.shop"), links: [[t("footer.allStores"), "/"], ["Amazon", "/store/amazon"], ["Flipkart", "/store/flipkart"], ["BigBasket", "/store/bigbasket"]] },
    { title: t("footer.yourAccount"), links: [[t("nav.orders"), "/orders"], [t("footer.wishlist"), "/wishlist"], [t("footer.addresses"), "/account/addresses"], [t("nav.signIn"), "/login"]] },
    { title: t("footer.help"), links: [[t("footer.helpFaq"), "/help"], [t("footer.contact"), "/contact"], [t("footer.howItWorks"), "/how-it-works"], [t("footer.shippingCustoms"), "/legal/shipping"], [t("footer.refunds"), "/legal/refunds"], [t("footer.track"), "https://track.skydrop.online"]] },
  ];
  return (
    <footer className="mt-16 border-t border-line bg-surface pb-24 md:pb-0">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-5">
        <div className="lg:col-span-2">
          <Logo className="text-lg" />
          <p className="mt-3 max-w-sm text-sm text-ink-2">
            {t("footer.about")}
          </p>
          <LangSwitch className="mt-4" />
          <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-ink-2">
            {[["shield", t("footer.badge.verified")], ["truck", t("footer.badge.door")], ["wallet", t("footer.badge.pay")]].map(([i, t]) => (
              <span key={t} className="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5">
                <Icon name={i} size={14} /> {t}
              </span>
            ))}
          </div>
        </div>
        {cols.map((c) => (
          <div key={c.title}>
            <h3 className="text-sm font-bold">{c.title}</h3>
            <ul className="mt-1 text-sm text-ink-2 md:mt-3 md:space-y-2">
              {c.links.map(([l, h]) => (
                <li key={l}>
                  <Link href={h} className="group inline-flex min-h-11 items-center gap-1 transition hover:text-accent md:min-h-0">
                    {l}
                    <Icon name="right" size={14} className="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <nav aria-label={t("footer.legal")} className="flex flex-wrap justify-center gap-x-1 border-t border-line px-4 py-2 text-xs font-semibold text-ink-2">
        {[[t("footer.terms"), "/legal/terms"], [t("footer.privacy"), "/legal/privacy"], [t("footer.refunds"), "/legal/refunds"], [t("footer.shipping"), "/legal/shipping"], [t("footer.contactShort"), "/contact"]].map(([l, h]) => (
          <Link key={h} href={h} className="inline-flex min-h-11 items-center rounded-full px-3 hover:text-accent">
            {l}
          </Link>
        ))}
      </nav>
      <div className="border-t border-line px-4 py-4 text-center text-xs text-ink-3">
        © {digits(new Date().getFullYear())} Skydrop Retail. {t("footer.disclaimer")}
        <span aria-hidden> · </span>
        <ConsentLink />
      </div>
      {/* Honeypot for scrapers (hidden from people and screen readers; robots.txt disallows /api). Following it blocks the IP. */}
      <a href="/api/catalog-export" rel="nofollow noindex" tabIndex={-1} aria-hidden="true" style={{ position: "absolute", left: "-10000px", width: 1, height: 1, overflow: "hidden" }}>
        Full catalog export (CSV)
      </a>
    </footer>
  );
}
