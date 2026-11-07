import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { GroupView } from "@/components/shopping/GroupView";
import { getFeatures, isOn } from "@/lib/features";
import { getT } from "@/i18n/server";

type Props = { params: Promise<{ code: string }> };

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("gp.joinMeta"), description: t("gp.joinDesc") };
}

export default async function GroupCodePage({ params }: Props) {
  const { code } = await params;
  if (!isOn(await getFeatures(), "group_orders") || !/^[A-Za-z0-9]{6,12}$/.test(code)) notFound();
  return (
    <div className="mx-auto max-w-xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <GroupView code={code.toUpperCase()} />
    </div>
  );
}
