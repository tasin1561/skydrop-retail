import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { RequestForm } from "@/components/shopping/RequestForm";
import { getFeatures, isOn } from "@/lib/features";

export const metadata: Metadata = { title: "Request a product", description: "Can't find it? Tell us and we'll source it from India for you." };

type SP = Promise<Record<string, string | string[] | undefined>>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";

export default async function RequestPage({ searchParams }: { searchParams: SP }) {
  if (!isOn(await getFeatures(), "product_requests")) notFound();
  const sp = await searchParams;
  const from = one(sp.from);
  return (
    <div className="mx-auto max-w-xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <span className="chip-icon h-12 w-12">
        <Icon name="search" size={22} />
      </span>
      <h1 className="mt-3 text-2xl font-extrabold tracking-tight">Request a product</h1>
      <p className="mt-1 text-sm text-ink-2">Can&apos;t find it here? Paste a link from any Indian store or describe it, and we&apos;ll find it for you.</p>
      <div className="mt-5">
        <RequestForm initialQuery={one(sp.q)} initialUrl={one(sp.url)} source={from === "search" ? "search" : from === "header" ? "header" : "web"} />
      </div>
    </div>
  );
}
