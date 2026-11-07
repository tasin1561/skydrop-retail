import type { Metadata } from "next";
import { AddressBook } from "@/components/account/AddressBook";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.addresses") };
}

export default function AddressesPage() {
  return <AddressBook />;
}
