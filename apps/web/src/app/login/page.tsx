import type { Metadata } from "next";
import { LoginFlow } from "@/components/account/LoginFlow";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.signIn"), description: t("meta.signInDesc") };
}

function safeNext(v: string | string[] | undefined) {
  const s = Array.isArray(v) ? v[0] : v;
  // Only same-site paths: no protocol-relative or absolute URLs.
  return s && s.startsWith("/") && !s.startsWith("//") && !s.startsWith("/\\") ? s : "/account";
}

export default async function LoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  return <LoginFlow next={safeNext(sp.next)} />;
}
