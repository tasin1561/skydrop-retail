// Mobile language state. The dictionaries (en.ts, bn.ts) and core.ts are copied from the web app by
// scripts/sync-i18n.mjs, so both apps share every key. English is the default; the choice is kept in
// AsyncStorage and, for signed-in shoppers, saved to the profile (PATCH /me { locale }) so SMS/email
// templates use the same language.
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { api } from "@/api/client";
import { useFeature } from "@/lib/features";
import { setFontLang } from "@/theme/fonts";
import { DEFAULT_LANG, isLang, hasKey as coreHasKey, localDigits, makeT as coreMakeT, type Lang, type MsgKey as CoreKey, type Translator as CoreTranslator, type Vars } from "./core";
import { mxBn, mxEn, type MxKey } from "./mobile";

// Everything from ./core except the names this file extends (hasKey, makeT, MsgKey, TFn, Translator).
export {
  BANGLA_DIGITS,
  DEFAULT_LANG,
  DICTS,
  LANGS,
  LANG_COOKIE,
  fmtBdt,
  fmtDate,
  fmtDateTime,
  fmtInr,
  fmtNum,
  fmtRelative,
  fmtTime,
  intlLocale,
  isLang,
  localDigits,
  translate,
} from "./core";
export type { Lang, Messages, Vars } from "./core";

// ---- mobile-only dictionary extension (src/i18n/mobile.ts) ----
// These local exports shadow the ones from ./core, so screens get one `t` that knows both the shared keys
// (generated from the web) and the "mx.*" app-only keys.
export type { MxKey };
export type MsgKey = CoreKey | MxKey;
export type TFn = (key: MsgKey, vars?: Vars) => string;
export type Translator = Omit<CoreTranslator, "t"> & { t: TFn };

const isMx = (k: string): k is MxKey => k in mxEn;

export function hasKey(k: string): k is MsgKey {
  return isMx(k) || coreHasKey(k);
}

/** Same rules as core `translate`: "one|other" plurals by vars.count, {name} placeholders, localised digits. */
function fillMx(lang: Lang, key: MxKey, vars?: Vars): string {
  let s: string = (lang === "bn" ? mxBn[key] : undefined) ?? mxEn[key];
  if (s.includes("|") && vars && typeof vars.count === "number") {
    const forms = s.split("|");
    s = (vars.count === 1 ? forms[0] : forms[forms.length - 1]) ?? s;
  }
  if (!vars) return s;
  return s.replace(/\{(\w+)\}/g, (m, k: string) => {
    const v = vars[k];
    if (v === undefined) return m;
    if (v === null) return "";
    return typeof v === "number" ? localDigits(String(v), lang) : v;
  });
}

const cache: Partial<Record<Lang, Translator>> = {};

/** Translator with the mobile extension (use this, not core makeT, in app code). */
export function makeT(lang: Lang): Translator {
  return (cache[lang] ??= (() => {
    const base = coreMakeT(lang);
    return { ...base, t: (key: MsgKey, vars?: Vars) => (isMx(key) ? fillMx(lang, key, vars) : base.t(key, vars)) };
  })());
}

const KEY = "skydrop:lang";

type Ctx = { lang: Lang; setLang: (l: Lang) => void; ready: boolean };
const LangCtx = createContext<Ctx>({ lang: DEFAULT_LANG, setLang: () => {}, ready: false });

export function LangProvider({ children }: { children: ReactNode }) {
  const bangla = useFeature("bangla");
  const [saved, setSaved] = useState<Lang>(DEFAULT_LANG);
  const [ready, setReady] = useState(false);
  const lang: Lang = bangla ? saved : "en";

  useEffect(() => {
    AsyncStorage.getItem(KEY)
      .then((v) => {
        if (isLang(v)) setSaved(v);
      })
      .catch(() => {})
      .finally(() => setReady(true));
  }, []);

  setFontLang(lang); // brandFont() picks Noto Sans Bengali families in Bangla mode

  const setLang = useCallback((l: Lang) => {
    setSaved(l);
    AsyncStorage.setItem(KEY, l).catch(() => {});
  }, []);

  const value = useMemo(() => ({ lang, setLang, ready }), [lang, setLang, ready]);
  return <LangCtx.Provider value={value}>{children}</LangCtx.Provider>;
}

export function useLang() {
  return useContext(LangCtx);
}

export function useT(): Translator {
  return makeT(useContext(LangCtx).lang);
}

/** Saves the language to the signed-in shopper's profile whenever it (or the account) changes. */
export function useLangSync(signedIn: boolean) {
  const { lang, ready } = useLang();
  const sent = useRef<string | null>(null);
  useEffect(() => {
    if (!ready || !signedIn || sent.current === lang) return;
    sent.current = lang;
    api.updateMe({ locale: lang }).catch(() => {
      sent.current = null;
    });
  }, [lang, ready, signedIn]);
}
