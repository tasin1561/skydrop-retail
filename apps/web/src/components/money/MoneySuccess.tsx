"use client";

import Link from "next/link";
import { Icon } from "@/components/Icon";
import "./money.css";

const COPY = {
  gift: {
    ok: ["Gift card sent", "We've sent the code by SMS / email. You can see it any time under Gift cards."],
    fail: ["Payment not completed", "Nothing was charged. You can try again with another method."],
    back: { href: "/gift-cards", label: "Back to gift cards" },
  },
  plus: {
    ok: ["Welcome to Skydrop Plus", "Your benefits are active now. Free shipping and member prices apply at checkout."],
    fail: ["Payment not completed", "Nothing was charged, and your plan has not changed."],
    back: { href: "/plus", label: "Back to Plus" },
  },
} as const;

export function MoneySuccess({ kind, status, orderId }: { kind: "gift" | "plus"; status?: string; orderId?: string }) {
  const ok = status === "paid";
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
      <h1 className="mt-5 text-2xl font-extrabold tracking-tight">{title}</h1>
      <p className="mt-2 text-sm text-ink-2">{body}</p>
      {orderId && <p className="mt-1 text-xs tabular-nums text-ink-3">Reference {orderId}</p>}
      <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <Link href={ok ? (kind === "plus" ? "/" : "/account/wallet") : c.back.href} className="btn btn-primary min-h-11">
          {ok ? (kind === "plus" ? "Start shopping" : "Open my wallet") : "Try again"}
        </Link>
        <Link href={c.back.href} className="btn btn-ghost min-h-11">
          {c.back.label}
        </Link>
      </div>
    </div>
  );
}
