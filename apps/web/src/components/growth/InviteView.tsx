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
import { useT } from "@/i18n/client";

const pad = (n: number) => String(n).padStart(2, "0");

export function InviteView() {
  const { t } = useT();
  const enabled = useFeature("referral");
  const { ready, user, live, data, error } = useReferrals();

  const body = !enabled ? (
    <NotAvailable title={t("iv.refer")} />
  ) : !ready ? (
    <LoadingBlock rows={3} />
  ) : !user ? (
    <SignedOut next="/account/invite" title={t("iv.signIn")} body={t("iv.signInB")} />
  ) : error ? (
    <div className="card p-6 text-center font-bold">{error}</div>
  ) : !data ? (
    <LoadingBlock rows={3} />
  ) : (
    <Invite data={data} live={live} />
  );

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title={t("acc.invite")} sub={t("iv.sub")} icon="spark" back={{ href: "/account", label: t("acc.account") }} />
      {body}
    </div>
  );
}

function Invite({ data, live }: { data: NonNullable<ReturnType<typeof useReferrals>["data"]>; live: boolean }) {
  const { inviterPoints, friendPoints } = data.rules;
  const { t, digits, date } = useT();
  const steps = [
    { icon: "share", title: t("iv.s1"), sub: t("iv.s1b") },
    { icon: "user", title: t("iv.s2"), sub: t("iv.s2b") },
    { icon: "truck", title: t("iv.s3"), sub: t("iv.s3b", { mine: inviterPoints, theirs: friendPoints }) },
  ];
  const progress = data.invited ? data.rewarded / data.invited : 0;

  return (
    <div className="space-y-4">
      {!live && (
        <p className="inline-flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs font-semibold text-ink-2">
          <Icon name="info" size={15} className="text-accent" /> {t("iv.demo")}
        </p>
      )}

      <section className="rise relative overflow-hidden rounded-[20px] p-5 text-white shadow-[var(--glow)]" style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}>
        <div aria-hidden className="gr-float absolute -right-6 -top-6 grid h-28 w-28 place-items-center rounded-full bg-white/10">
          <Icon name="spark" size={44} />
        </div>
        <p className="relative text-sm text-white/85">{t("iv.give", { theirs: friendPoints, mine: inviterPoints })}</p>
        <p className="relative mt-1 text-xs font-semibold uppercase tracking-widest text-white/75">{t("iv.code")}</p>
        <p className="relative font-mono text-3xl font-extrabold tracking-[0.2em]">{data.code}</p>
        <div className="relative mt-4 flex flex-wrap gap-2">
          <ShareButton
            variant="ghost"
            label={t("iv.share")}
            path="/"
            title="Skydrop Retail"
            text={t("iv.text", { points: friendPoints })}
            className="!border-white/40 !bg-white !text-slate-900"
            qr
          />
          <CopyButton text={data.link} label={t("common.copyLink")} className="btn min-h-11 border border-white/40 bg-white/10 px-4 text-sm font-bold text-white" />
        </div>
      </section>

      <section aria-label={t("iv.yours")} className="grid grid-cols-3 gap-3">
        {[
          { label: t("iv.invited"), value: digits(pad(data.invited)), icon: "user" },
          { label: t("iv.first"), value: digits(pad(data.rewarded)), icon: "truck" },
          { label: t("iv.earned"), value: digits(data.pointsEarned), icon: "spark" },
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
          {t("iv.how")}
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
                  {digits(pad(i + 1))} · {s.title}
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
            {t("iv.friends")}
          </h2>
          {data.invited > 0 && (
            <>
              <div className="ac-bar mt-2" data-tone="good" role="progressbar" aria-label={t("iv.progress")} aria-valuemin={0} aria-valuemax={data.invited} aria-valuenow={data.rewarded}>
                <span style={{ ["--p" as string]: progress }} />
              </div>
              <p className="mt-1 text-xs text-ink-2">
                {t("iv.ofGot", { n: data.rewarded, total: data.invited })}
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
                  <span className="block text-xs text-ink-2">{t("iv.joined", { date: date(f.joinedAt) })}</span>
                </span>
                <span className="ac-chip" data-tone={f.status === "rewarded" ? "good" : "warn"}>
                  {f.status === "rewarded" ? t("iv.plus", { count: inviterPoints }) : t("iv.waiting")}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="px-4 pb-4 pt-2 text-sm text-ink-2">{t("iv.none")}</p>
        )}
      </section>
      <p className="text-center text-xs text-ink-3">{t("iv.once")}</p>
    </div>
  );
}
