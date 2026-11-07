// Server components: `const { t, bdt } = await getT()`. The language comes from the sr-lang cookie
// (set by the EN | বাংলা switch); English when the cookie is missing or the bangla feature is off.
import { cache } from "react";
import { cookies } from "next/headers";
import { getFeatures, isOn } from "@/lib/features";
import { DEFAULT_LANG, LANG_COOKIE, isLang, makeT, type Lang, type Translator } from "./core";

export const getLang = cache(async (): Promise<Lang> => {
  const [jar, features] = await Promise.all([cookies(), getFeatures()]);
  if (!isOn(features, "bangla")) return DEFAULT_LANG;
  const v = jar.get(LANG_COOKIE)?.value;
  return isLang(v) ? v : DEFAULT_LANG;
});

export async function getT(): Promise<Translator> {
  return makeT(await getLang());
}
