import type { Metadata } from "next";
import { OrdersList } from "@/components/account/OrdersList";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.orders") };
}

const TABS = ["all", "active", "delivered", "cancelled"] as const;
type Tab = (typeof TABS)[number];

export default async function OrdersPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  const t = Array.isArray(sp.tab) ? sp.tab[0] : sp.tab;
  const tab: Tab = (TABS as readonly string[]).includes(t ?? "") ? (t as Tab) : "all";
  return <OrdersList initialTab={tab} />;
}
