import type { Metadata } from "next";
import { LoginFlow } from "@/components/account/LoginFlow";

export const metadata: Metadata = {
  title: "Sign in",
  description: "Sign in to Skydrop Retail with your Bangladesh mobile number.",
};

function safeNext(v: string | string[] | undefined) {
  const s = Array.isArray(v) ? v[0] : v;
  // Only same-site paths: no protocol-relative or absolute URLs.
  return s && s.startsWith("/") && !s.startsWith("//") && !s.startsWith("/\\") ? s : "/account";
}

export default async function LoginPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  return <LoginFlow next={safeNext(sp.next)} />;
}
