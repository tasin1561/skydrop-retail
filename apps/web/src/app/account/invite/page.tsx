import type { Metadata } from "next";
import { InviteView } from "@/components/growth/InviteView";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.invite"), robots: { index: false } };
}

export default function InvitePage() {
  return <InviteView />;
}
