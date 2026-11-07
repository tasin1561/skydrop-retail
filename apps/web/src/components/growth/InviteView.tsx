"use client";

import "./growth.css";
import { Icon } from "@/components/Icon";
import { CopyButton } from "@/components/share/CopyButton";
import { ShareButton } from "@/components/share/ShareButton";
import { LoadingBlock, PageHead } from "@/components/account/ui";
import { SignedOut } from "@/components/account/SignedOut";
import { useFeature } from "@/components/features/FeaturesProvider";
import { NotAvailable } from "./NotAvailable";
import { useReferrals } from "./useGrowth";

const pad = (n: number) => String(n).padStart(2, "0");

export function InviteView() {
  const enabled = useFeature("referral");
  const { ready, user, live, data, error } = useReferrals();

  const body = !enabled ? (
    <NotAvailable title="Refer a friend" />
  ) : !ready ? (
    <LoadingBlock rows={3} />
  ) : !user ? (
    <SignedOut next="/account/invite" title="Sign in to invite friends" body="Get Skydrop Points when friends you invite get their first order." />
  ) : error ? (
    <div className="card p-6 text-center font-bold">{error}</div>
  ) : !data ? (
    <LoadingBlock rows={3} />
  ) : (
    <Invite data={data} live={live} />
  );

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title="Invite friends" sub="Share Skydrop Retail, both of you earn points" icon="spark" back={{ href: "/account", label: "Account" }} />
      {body}
    </div>
  );
}

function Invite({ data, live }: { data: NonNullable<ReturnType<typeof useReferrals>["data"]>; live: boolean }) {
  const { inviterPoints, friendPoints } = data.rules;
  const steps = [
    { icon: "share", title: "Share your link", sub: "On WhatsApp, Messenger or anywhere you like" },
    { icon: "user", title: "Your friend signs up", sub: "With your link or your code at sign-in" },
    { icon: "truck", title: "Their first order arrives", sub: `You get ${inviterPoints} points, they get ${friendPoints}` },
  ];
  const progress = data.invited ? data.rewarded / data.invited : 0;

  return (
    <div className="space-y-4">
      {!live && (
        <p className="inline-flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs font-semibold text-ink-2">
          <Icon name="info" size={15} className="text-accent" /> Demo data: friends and points below are examples.
        </p>
      )}

      <section className="rise relative overflow-hidden rounded-[20px] p-5 text-white shadow-[var(--glow)]" style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}>
        <div aria-hidden className="gr-float absolute -right-6 -top-6 grid h-28 w-28 place-items-center rounded-full bg-white/10">
          <Icon name="spark" size={44} />
        </div>
        <p className="relative text-sm text-white/85">Give {friendPoints} points, get {inviterPoints} points</p>
        <p className="relative mt-1 text-xs font-semibold uppercase tracking-widest text-white/75">Your code</p>
        <p className="relative font-mono text-3xl font-extrabold tracking-[0.2em]">{data.code}</p>
        <div className="relative mt-4 flex flex-wrap gap-2">
          <ShareButton
            variant="ghost"
            label="Share invite"
            path="/"
            title="Skydrop Retail"
            text={`Shop amazon.in, Flipkart and BigBasket from Bangladesh with Skydrop Retail. Use my link and get ${friendPoints} points after your first order:`}
            className="!border-white/40 !bg-white !text-slate-900"
            qr
          />
          <CopyButton text={data.link} label="Copy link" className="btn min-h-11 border border-white/40 bg-white/10 px-4 text-sm font-bold text-white" />
        </div>
      </section>

      <section aria-label="Your invites" className="grid grid-cols-3 gap-3">
        {[
          { label: "Invited", value: pad(data.invited), icon: "user" },
          { label: "First orders", value: pad(data.rewarded), icon: "truck" },
          { label: "Points earned", value: String(data.pointsEarned), icon: "spark" },
        ].map((s, i) => (
          <div key={s.label} className="card rise flex flex-col gap-2 p-3 sm:p-4" style={{ ["--i" as string]: i + 1 }}>
            <span className="chip-icon">
              <Icon name={s.icon} size={18} />
            </span>
            <span className="text-xl font-extrabold tabular-nums">{s.value}</span>
            <span className="text-xs font-semibold text-ink-2">{s.label}</span>
          </div>
        ))}
      </section>

      <section className="card rise p-4" aria-labelledby="inv-how" style={{ ["--i" as string]: 4 }}>
        <h2 id="inv-how" className="text-sm font-extrabold">
          How it works
        </h2>
        <ol className="gr-steps mt-3">
          {steps.map((s, i) => (
            <li key={s.title} className="gr-step" data-state={i === 0 ? "current" : "next"}>
              <span className="relative">
                <span className={"chip-icon h-10 w-10 " + (i === 0 ? "is-active" : "")}>
                  <Icon name={s.icon} size={18} />
                </span>
                <span className="gr-ring" aria-hidden />
              </span>
              <span>
                <span className="block text-sm font-bold">
                  {pad(i + 1)} · {s.title}
                </span>
                <span className="block text-xs text-ink-2">{s.sub}</span>
              </span>
            </li>
          ))}
        </ol>
      </section>

      <section className="card rise overflow-hidden" aria-labelledby="inv-friends" style={{ ["--i" as string]: 5 }}>
        <div className="px-4 pt-4">
          <h2 id="inv-friends" className="text-sm font-extrabold">
            Friends
          </h2>
          {data.invited > 0 && (
            <>
              <div className="ac-bar mt-2" data-tone="good" role="progressbar" aria-label="Friends with a delivered first order" aria-valuemin={0} aria-valuemax={data.invited} aria-valuenow={data.rewarded}>
                <span style={{ ["--p" as string]: progress }} />
              </div>
              <p className="mt-1 text-xs text-ink-2">
                {data.rewarded} of {data.invited} got their first order
              </p>
            </>
          )}
        </div>
        {data.friends.length ? (
          <ul className="mt-2">
            {data.friends.map((f, i) => (
              <li key={i} className="ac-row">
                <span className="chip-icon shrink-0">
                  <Icon name="user" size={17} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-semibold">{f.name}</span>
                  <span className="block text-xs text-ink-2">Joined {new Date(f.joinedAt).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}</span>
                </span>
                <span className="ac-chip" data-tone={f.status === "rewarded" ? "good" : "warn"}>
                  {f.status === "rewarded" ? `+${inviterPoints} points` : "Waiting for first order"}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="px-4 pb-4 pt-2 text-sm text-ink-2">No friends yet. Share your link to get started.</p>
        )}
      </section>
      <p className="text-center text-xs text-ink-3">Points are added once per friend, after their first order is delivered.</p>
    </div>
  );
}
