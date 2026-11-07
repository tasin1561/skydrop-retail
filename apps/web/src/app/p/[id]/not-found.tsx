import Link from "next/link";
import { Icon } from "@/components/Icon";
import { getT } from "@/i18n/server";

export default async function ProductNotFound() {
  const { t } = await getT();
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-4 py-16 text-center">
      <span className="pop grid h-20 w-20 place-items-center rounded-[24px] bg-[var(--accent-soft)] text-accent shadow-[var(--glow)]">
        <Icon name="package" size={38} stroke={1.5} />
      </span>
      <h1 className="mt-5 text-2xl font-extrabold tracking-tight">{t("pd.nf.title")}</h1>
      <p className="mt-2 text-sm text-ink-2">
        {t("pd.nf.body")}
      </p>
      <div className="mt-6 flex w-full flex-col gap-2.5 sm:flex-row sm:justify-center">
        <Link href="/search?q=" className="btn btn-primary">
          <Icon name="search" size={18} /> {t("cat.searchProducts")}
        </Link>
        <Link href="/" className="btn btn-ghost">
          <Icon name="home" size={18} /> {t("common.backHome")}
        </Link>
      </div>
    </div>
  );
}
