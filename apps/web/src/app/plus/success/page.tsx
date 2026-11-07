import type { Metadata } from "next";
import { MoneySuccess } from "@/components/money/MoneySuccess";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.plus"), robots: { index: false } };
}

const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export default async function PlusSuccessPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  return <MoneySuccess kind="plus" status={one(sp.status)} orderId={one(sp.order)} />;
}
