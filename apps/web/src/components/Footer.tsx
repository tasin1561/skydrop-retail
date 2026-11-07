import Link from "next/link";
import { Icon, Logo } from "./Icon";
import { ConsentLink } from "./tracking/ConsentLink";

export function Footer() {
  const cols = [
    { title: "Shop", links: [["All stores", "/"], ["Mega Store", "/store/amazon"], ["Style Bazaar", "/store/flipkart"], ["Daily Basket", "/store/bigbasket"]] },
    { title: "Your account", links: [["Orders", "/orders"], ["Wishlist", "/wishlist"], ["Addresses", "/account/addresses"], ["Sign in", "/login"]] },
    { title: "Help", links: [["Help and FAQ", "/help"], ["Contact us", "/contact"], ["How it works", "/how-it-works"], ["Shipping and customs", "/legal/shipping"], ["Refunds", "/legal/refunds"], ["Track a parcel", "https://track.skydrop.online"]] },
  ];
  return (
    <footer className="mt-16 border-t border-line bg-surface pb-24 md:pb-0">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-5">
        <div className="lg:col-span-2">
          <Logo className="text-lg" />
          <p className="mt-3 max-w-sm text-sm text-ink-2">
            Shop Indian stores from Bangladesh. We buy the item in India, Skydrop brings it across the border and to your door.
          </p>
          <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-ink-2">
            {[["shield", "Verified price at checkout"], ["truck", "Door delivery, all 64 districts"], ["wallet", "bKash, Nagad, cards"]].map(([i, t]) => (
              <span key={t} className="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5">
                <Icon name={i} size={14} /> {t}
              </span>
            ))}
          </div>
        </div>
        {cols.map((c) => (
          <div key={c.title}>
            <h3 className="text-sm font-bold">{c.title}</h3>
            <ul className="mt-3 space-y-2 text-sm text-ink-2">
              {c.links.map(([l, h]) => (
                <li key={l}>
                  <Link href={h} className="group inline-flex items-center gap-1 transition hover:text-accent">
                    {l}
                    <Icon name="right" size={14} className="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <nav aria-label="Legal" className="flex flex-wrap justify-center gap-x-1 border-t border-line px-4 py-2 text-xs font-semibold text-ink-2">
        {[["Terms", "/legal/terms"], ["Privacy", "/legal/privacy"], ["Refunds", "/legal/refunds"], ["Shipping", "/legal/shipping"], ["Contact", "/contact"]].map(([l, h]) => (
          <Link key={h} href={h} className="inline-flex min-h-11 items-center rounded-full px-3 hover:text-accent">
            {l}
          </Link>
        ))}
      </nav>
      <div className="border-t border-line px-4 py-4 text-center text-xs text-ink-3">
        © {new Date().getFullYear()} Skydrop Retail. Independent reseller; product names and brands belong to their owners. Products are purchased from the named Indian store on your behalf.
        <span aria-hidden> · </span>
        <ConsentLink />
      </div>
    </footer>
  );
}
