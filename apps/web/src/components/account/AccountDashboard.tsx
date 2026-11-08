"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { currentStatus, isInProgress } from "@/lib/orderStatus";
import { ConfirmDialog, LoadingBlock, useNow } from "./ui";
import { SignedOut } from "./SignedOut";
import { prettyPhone } from "./phone";
import { AccountMoney, PlusBadge } from "@/components/money/AccountMoney";
import { Feature } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";
import { bdName } from "@/lib/bd";
import { LangSwitch } from "@/components/LangSwitch";
import { useFeature } from "@/components/features/FeaturesProvider";

type Theme = "light" | "dark" | "system";
const THEMES: { id: Theme; label: MsgKey; icon: string }[] = [
  { id: "light", label: "acc.light", icon: "sun" },
  { id: "dark", label: "acc.dark", icon: "moon" },
  { id: "system", label: "acc.system", icon: "settings" },
];

function greeting(now: number): MsgKey {
  if (!now) return "acc.hello";
  const h = new Date(now).getHours();
  return h < 12 ? "acc.morning" : h < 17 ? "acc.afternoon" : "acc.evening";
}

export function AccountDashboard() {
  const { ready, user, orders, wishlist, addresses, theme, setTheme, logout } = useStore();
  const toast = useToast();
  const { t: tr, digits, lang } = useT();
  const bangla = useFeature("bangla");
  const router = useRouter();
  const now = useNow(60_000);
  const [confirmOut, setConfirmOut] = useState(false);

  if (!ready)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
        <LoadingBlock rows={3} />
      </div>
    );

  if (!user)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 md:pb-12">
        <SignedOut next="/account" title={tr("acc.signInTitle")} body={tr("acc.signInBody")} />
      </div>
    );

  const inTransit = orders.filter((o) => isInProgress(currentStatus(o, now))).length;
  const def = addresses.find((a) => a.isDefault) ?? addresses[0];
  const themeIdx = THEMES.findIndex((t) => t.id === theme);
  const initials = user.name
    .split(/\s+/)
    .map((w) => w[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  const stats = [
    { label: tr("acc.orders"), value: orders.length, icon: "receipt", href: "/orders" },
    { label: tr("acc.inTransit"), value: inTransit, icon: "truck", href: "/orders?tab=active" },
    { label: tr("acc.wishlist"), value: wishlist.length, icon: "heart", href: "/wishlist" },
  ];

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      {/* greeting */}
      <section
        className="rise relative overflow-hidden rounded-[12px] p-5 text-white shadow-[var(--glow)]"
        style={{ background: "linear-gradient(135deg, var(--hero-a) 0%, var(--hero-a) 55%, color-mix(in srgb, var(--hero-a) 72%, var(--hero-b)) 100%)" }}
      >
        <div aria-hidden className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10" />
        <div aria-hidden className="absolute -bottom-16 right-16 h-32 w-32 rounded-full bg-white/10" />
        <div className="relative flex items-center gap-4">
          <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/20 text-lg font-extrabold backdrop-blur-sm">
            {initials || <Icon name="user" />}
          </span>
          <div className="min-w-0">
            <p className="text-sm text-white/80">{tr(greeting(now))},</p>
            <h1 className="flex items-center gap-2 text-2xl font-extrabold tracking-tight">
              <span className="truncate">{user.name}</span> <PlusBadge />
            </h1>
            <p className="text-sm tabular-nums text-white/80">{prettyPhone(user.phone)}</p>
          </div>
        </div>
      </section>

      {/* quick stats */}
      <section aria-label={tr("acc.stats")} className="mt-4 grid grid-cols-3 gap-3">
        {stats.map((s, i) => (
          <Link
            key={s.label}
            href={s.href}
            className="card rise group flex flex-col gap-2 p-3 transition-transform hover:-translate-y-0.5 hover:shadow-[var(--shadow-2)] sm:p-4"
            style={{ ["--i" as string]: i + 1 }}
          >
            <span className="chip-icon transition">
              <Icon name={s.icon} size={18} />
            </span>
            <span className="text-2xl font-extrabold tabular-nums tracking-tight">{digits(String(s.value).padStart(2, "0"))}</span>
            <span className="text-xs font-semibold text-ink-2">{s.label}</span>
          </Link>
        ))}
      </section>

      {/* money module: wallet balance + Skydrop Plus status / savings */}
      <AccountMoney />

      {/* menu */}
      <section aria-label={tr("acc.menu")} className="card rise mt-4 overflow-hidden" style={{ ["--i" as string]: 4 }}>
        <Row href="/orders" icon="receipt" label={tr("acc.orders")} sub={tr("acc.ordersSub")} badge={orders.length} />
        <Row
          href="/account/addresses"
          icon="pin"
          label={tr("acc.addresses")}
          sub={def ? `${def.area || bdName(def.district, lang)}, ${bdName(def.district, lang)}` : tr("acc.addAddress")}
          badge={addresses.length}
        />
        <Row href="/wishlist" icon="heart" label={tr("acc.wishlist")} sub={tr("acc.wishSub")} badge={wishlist.length} />
        <Row href="/account/points" icon="spark" label={tr("acc.points")} sub={tr("acc.pointsSub")} />
        <Feature name="wallet">
          <Row href="/account/wallet" icon="wallet" label={tr("acc.wallet")} sub={tr("acc.walletSub")} />
        </Feature>
        <Feature name="gift_cards">
          <Row href="/gift-cards" icon="bag" label={tr("acc.gift")} sub={tr("acc.giftSub")} />
        </Feature>
        <Feature name="membership">
          <Row href="/plus" icon="star" label={tr("acc.plus")} sub={tr("acc.plusSub")} />
        </Feature>
        <Feature name="referral">
          <Row href="/account/invite" icon="share" label={tr("acc.invite")} sub={tr("acc.inviteSub")} />
        </Feature>
        <Feature name="affiliate">
          <Row href="/account/affiliate" icon="tag" label={tr("acc.affiliate")} sub={tr("acc.affiliateSub")} />
        </Feature>
        <Row icon="card" label={tr("acc.payment")} sub={tr("acc.paymentSub")} soon />
        <Row icon="info" label={tr("acc.notif")} sub={tr("acc.notifSub")} soon />
        <Row href="/how-it-works" icon="help" label={tr("acc.help")} sub={tr("acc.helpSub")} />
      </section>

      {/* theme */}
      <section aria-label={tr("acc.appearance")} className="card rise mt-4 p-4" style={{ ["--i" as string]: 5 }}>
        <div className="mb-3 flex items-center gap-3">
          <span className="chip-icon">
            <Icon name={theme === "dark" ? "moon" : theme === "light" ? "sun" : "settings"} size={18} />
          </span>
          <div>
            <h2 className="text-sm font-bold">{tr("acc.theme")}</h2>
            <p className="text-xs text-ink-2">{tr("acc.currently", { name: tr(THEMES[themeIdx]?.label ?? "acc.system") })}</p>
          </div>
        </div>
        <div role="radiogroup" aria-label={tr("acc.theme")} className="ac-tabs" style={{ ["--n" as string]: THEMES.length }}>
          <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: Math.max(0, themeIdx) }} />
          {THEMES.map((t, i) => (
            <button
              key={t.id}
              type="button"
              role="radio"
              aria-checked={t.id === theme}
              tabIndex={t.id === theme ? 0 : -1}
              className="ac-tab min-h-11"
              onClick={() => setTheme(t.id)}
              onKeyDown={(e) => {
                const d = e.key === "ArrowRight" || e.key === "ArrowDown" ? 1 : e.key === "ArrowLeft" || e.key === "ArrowUp" ? -1 : 0;
                if (!d) return;
                e.preventDefault();
                const n = THEMES[(i + d + THEMES.length) % THEMES.length];
                setTheme(n.id);
                (e.currentTarget.parentElement?.querySelectorAll("button")[THEMES.indexOf(n)] as HTMLElement | undefined)?.focus();
              }}
            >
              <Icon name={t.icon} size={16} /> {tr(t.label)}
            </button>
          ))}
        </div>
        {bangla && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
            <div className="flex min-w-0 items-center gap-3">
              <span className="chip-icon">
                <Icon name="globe" size={18} />
              </span>
              <div className="min-w-0">
                <h2 className="text-sm font-bold">{tr("acc.language")}</h2>
                <p className="text-xs text-ink-2">{tr("acc.languageSub")}</p>
              </div>
            </div>
            <LangSwitch />
          </div>
        )}
      </section>

      {/* sign out */}
      <section className="card rise mt-4 overflow-hidden" style={{ ["--i" as string]: 6 }}>
        <button type="button" className="ac-row w-full text-left" onClick={() => setConfirmOut(true)}>
          <span className="chip-icon" style={{ background: "color-mix(in srgb, var(--bad) 12%, transparent)", color: "var(--bad)" }}>
            <Icon name="logout" size={18} />
          </span>
          <span className="flex-1 font-semibold text-bad">{tr("acc.signOut")}</span>
          <Icon name="right" size={18} className="ac-chev" />
        </button>
      </section>
      <p className="mt-4 text-center text-xs text-ink-3">{tr("acc.demo")}</p>

      {confirmOut && (
        <ConfirmDialog
          title={tr("acc.signOutQ")}
body={tr("acc.signOutBody")}
confirmLabel={tr("acc.signOut")}
          icon="logout"
          danger
          onClose={() => setConfirmOut(false)}
          onConfirm={() => {
            logout();
            toast({ kind: "info", title: tr("acc.signedOut"), body: tr("acc.seeYou") });
            router.push("/");
          }}
        />
      )}
    </div>
  );
}

function Row({
  href,
  icon,
  label,
  sub,
  badge,
  soon,
}: {
  href?: string;
  icon: string;
  label: string;
  sub?: string;
  badge?: number;
  soon?: boolean;
}) {
  const { t: tr, digits } = useT();
  const inner = (
    <>
      <span className="chip-icon shrink-0">
        <Icon name={icon} size={18} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{label}</span>
        {sub && <span className="block truncate text-xs text-ink-2">{sub}</span>}
      </span>
      {soon ? (
        <span className="ac-chip" data-tone="muted">
          {tr("acc.soon")}
        </span>
      ) : typeof badge === "number" ? (
        <span className="ac-chip tabular-nums" aria-label={tr("acc.badge", { count: badge })}>
          {digits(String(badge).padStart(2, "0"))}
        </span>
      ) : null}
      {!soon && <Icon name="right" size={18} className="ac-chev shrink-0" />}
    </>
  );
  if (soon || !href)
    return (
      <div className="ac-row" aria-disabled="true">
        {inner}
      </div>
    );
  return (
    <Link href={href} className="ac-row">
      {inner}
    </Link>
  );
}
