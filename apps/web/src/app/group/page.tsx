import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { GroupStart } from "@/components/shopping/GroupStart";
import { getFeatures, isOn } from "@/lib/features";
import { isStoreMode } from "@/lib/markets";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("grp.groupBuy"), description: t("gp.metaDesc") };
}

export default async function GroupPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "group_orders")) notFound();
  const raw = (await searchParams).market;
  const m = Array.isArray(raw) ? raw[0] : raw;
  const { t } = await getT();
  return (
    <div className="mx-auto max-w-xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <span className="chip-icon h-12 w-12">
        <Icon name="user" size={22} />
      </span>
      <h1 className="mt-3 text-2xl font-extrabold tracking-tight">{t("grp.groupBuy")}</h1>
      <p className="mt-1 text-sm text-ink-2">{t("gp.sub")}</p>
      <div className="mt-5">
        <GroupStart initialMarket={isStoreMode(m) && m !== "all" ? m : undefined} />
      </div>
    </div>
  );
}
