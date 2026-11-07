import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { RequestForm } from "@/components/shopping/RequestForm";
import { getFeatures, isOn } from "@/lib/features";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("common.requestProduct"), description: t("rqp.metaDesc") };
}

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";

export default async function RequestPage({ searchParams }: { searchParams: SP }) {
  if (!isOn(await getFeatures(), "product_requests")) notFound();
  const sp = await searchParams;
  const from = one(sp.from);
  const { t } = await getT();
  return (
    <div className="mx-auto max-w-xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <span className="chip-icon h-12 w-12">
        <Icon name="search" size={22} />
      </span>
      <h1 className="mt-3 text-2xl font-extrabold tracking-tight">{t("common.requestProduct")}</h1>
      <p className="mt-1 text-sm text-ink-2">{t("rqp.sub")}</p>
      <div className="mt-5">
        <RequestForm initialQuery={one(sp.q)} initialUrl={one(sp.url)} source={from === "search" ? "search" : from === "header" ? "header" : "web"} />
      </div>
    </div>
  );
}
