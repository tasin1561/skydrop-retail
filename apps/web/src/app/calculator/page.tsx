import type { Metadata } from "next";
import { CostCalculator } from "@/components/calculator/CostCalculator";
import { Icon } from "@/components/Icon";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("calc.title"), description: t("calc.sub") };
}

export default async function CalculatorPage() {
  const { t } = await getT();
  return (
    <div className="mx-auto max-w-4xl px-4 pb-24 pt-4 sm:pt-8 md:pb-10">
      <span className="chip-icon h-12 w-12">
        <Icon name="calc" size={22} />
      </span>
      <h1 className="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{t("calc.title")}</h1>
      <p className="mt-1 max-w-2xl text-sm text-ink-2 sm:text-base">{t("calc.sub")}</p>
      <div className="mt-5">
        <CostCalculator />
      </div>
    </div>
  );
}
