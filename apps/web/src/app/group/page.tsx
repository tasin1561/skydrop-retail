import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { GroupStart } from "@/components/shopping/GroupStart";
import { getFeatures, isOn } from "@/lib/features";
import { isStoreMode } from "@/lib/markets";

export const metadata: Metadata = { title: "Group buy", description: "Order with friends from one Indian store and share the shipping." };

export default async function GroupPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "group_orders")) notFound();
  const raw = (await searchParams).market;
  const m = Array.isArray(raw) ? raw[0] : raw;
  return (
    <div className="mx-auto max-w-xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <span className="chip-icon h-12 w-12">
        <Icon name="user" size={22} />
      </span>
      <h1 className="mt-3 text-2xl font-extrabold tracking-tight">Group buy</h1>
      <p className="mt-1 text-sm text-ink-2">Order from one store with friends. We ship it as one parcel and split the shipping, so everyone saves.</p>
      <div className="mt-5">
        <GroupStart initialMarket={isStoreMode(m) && m !== "all" ? m : undefined} />
      </div>
    </div>
  );
}
