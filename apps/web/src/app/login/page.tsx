import type { Metadata } from "next";
import { LoginFlow } from "@/components/account/LoginFlow";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.signIn"), description: t("meta.signInDesc") };
}

function safeNext(v: string | string[] | undefined) {
  const s = Array.isArray(v) ? v[0] : v;
  // Only same-site paths. Resolve it the way the browser will: "/\t/evil.com" or "/\\evil.com" pass a prefix check but
  // the URL parser drops tabs/newlines and treats "\" as "/", turning them into the protocol-relative //evil.com.
  if (!s || !s.startsWith("/")) return "/account";
  try {
    const base = new URL("https://retail.invalid");
    const u = new URL(s, base);
    return u.origin === base.origin ? u.pathname + u.search + u.hash : "/account";
  } catch {
    return "/account";
  }
}

export default async function LoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  return <LoginFlow next={safeNext(sp.next)} />;
}
