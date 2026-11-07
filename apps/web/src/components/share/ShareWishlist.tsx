"use client";

import { apiEnabled } from "@/lib/apiClient";
import { demoWishlistCode, growthApi } from "@/lib/growth";
import { useStore } from "@/state/store";
import { ShareButton } from "./ShareButton";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";

/** "Share my wishlist": a public /w/{code} page listing the saved products. */
export function ShareWishlist({ ids }: { ids: string[] }) {
  const { token, user } = useStore();
  const first = user?.name.split(" ")[0];
  const enabled = useFeature("share");
  const { t } = useT();
  if (!enabled) return null;

  return (
    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
      <p className="min-w-0 text-sm text-ink-2">
        <span className="block font-bold text-ink">{t("sw.planning")}</span>
        {t("sw.share")}
      </p>
      <ShareButton
        variant="ghost"
        label={t("sw.label")}
        title={first ? t("w.owners", { owner: first }) : t("sw.mine")}
        text={first ? t("sw.text", { name: first }) : t("sw.myText")}
        qr
        resolvePath={async () => {
          if (apiEnabled && token) {
            const r = await growthApi.shareWishlist();
            return `/w/${r.code}`;
          }
          return `/w/${demoWishlistCode(ids)}`; // demo (or signed out): the ids travel in the link
        }}
      />
    </div>
  );
}
