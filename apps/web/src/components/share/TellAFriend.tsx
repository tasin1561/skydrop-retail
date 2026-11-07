"use client";

import { ShareButton } from "./ShareButton";
import { useShareCodes } from "./useShareCodes";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";

/** Order success: invite friends (invite code, or affiliate ref for approved affiliates). */
export function TellAFriend({ style }: { style?: React.CSSProperties }) {
  const codes = useShareCodes();
  const enabled = useFeature("share");
  const { t } = useT();
  if (!enabled) return null;
  const body = codes.ref
    ? t("taf.aff")
    : t("taf.ref");

  return (
    <section className="co-card rise mt-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center" style={style} aria-labelledby="taf-h">
      <span className="chip-icon is-active h-11 w-11 shrink-0 rounded-2xl">
        <Icon name="spark" size={20} />
      </span>
      <div className="min-w-0 flex-1">
        <h2 id="taf-h" className="text-sm font-extrabold">
          {t("taf.title")}
        </h2>
        <p className="text-xs text-ink-2">{body}</p>
      </div>
      <ShareButton
        variant="ghost"
        label={t("acc.invite")}
        path="/"
        title="Skydrop Retail"
        text={t("taf.text")}
        qr
      />
    </section>
  );
}
