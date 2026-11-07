import type { Metadata } from "next";
import { WishlistGrid } from "@/components/account/WishlistGrid";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.wishlist") };
}

export default function WishlistPage() {
  return <WishlistGrid />;
}
