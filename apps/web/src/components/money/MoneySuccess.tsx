"use client";

import Link from "next/link";
import { Icon } from "@/components/Icon";
import "./money.css";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const COPY = {
  gift: {
    ok: ["ms.giftOk", "ms.giftOkB"] as MsgKey[],
    fail: ["ms.fail", "ms.giftFailB"] as MsgKey[],
    back: { href: "/gift-cards", label: "ms.backGift" as MsgKey },
  },
  plus: {
    ok: ["ms.plusOk", "ms.plusOkB"] as MsgKey[],
    fail: ["ms.fail", "ms.plusFailB"] as MsgKey[],
    back: { href: "/plus", label: "ms.backPlus" as MsgKey },
  },
} as const;

export function MoneySuccess({ kind, status, orderId }: { kind: "gift" | "plus"; status?: string; orderId?: string }) {
  const ok = status === "paid";
  const { t } = useT();
  const c = COPY[kind];
  const [title, body] = ok ? c.ok : c.fail;
  return (
    <div className="mx-auto max-w-md px-4 pb-24 pt-12 text-center md:pb-12">
      <span
        className={"pop mx-auto grid h-16 w-16 place-items-center rounded-2xl text-white shadow-[var(--glow)] " + (ok ? "bg-good" : "bg-warn")}
        aria-hidden
      >
        <Icon name={ok ? (kind === "plus" ? "star" : "check") : "info"} size={30} stroke={2.6} />
      </span>
      <h1 className="mt-5 text-2xl font-extrabold tracking-tight">{t(title)}</h1>
      <p className="mt-2 text-sm text-ink-2">{t(body)}</p>
      {orderId && <p className="mt-1 text-xs tabular-nums text-ink-3">{t("ms.ref", { id: orderId })}</p>}
      <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <Link href={ok ? (kind === "plus" ? "/" : "/account/wallet") : c.back.href} className="btn btn-primary min-h-11">
          {ok ? (kind === "plus" ? t("hero.start") : t("ms.openWallet")) : t("common.tryAgain")}
        </Link>
        <Link href={c.back.href} className="btn btn-ghost min-h-11">
          {t(c.back.label)}
        </Link>
      </div>
    </div>
  );
}
