import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { MyRequests } from "@/components/shopping/MyRequests";
import { getFeatures, isOn } from "@/lib/features";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.requests") };
}

export default async function AccountRequestsPage() {
  if (!isOn(await getFeatures(), "product_requests")) notFound();
  const { t } = await getT();
  return (
    <div className="mx-auto max-w-2xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <div className="mb-4 flex items-end justify-between gap-3">
        <h1 className="text-2xl font-extrabold tracking-tight">{t("meta.requests")}</h1>
        <Link href="/request" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-accent">
          <Icon name="plus" size={16} /> {t("req.new")}
        </Link>
      </div>
      <MyRequests />
    </div>
  );
}
