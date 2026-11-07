// Mobile language state. The dictionaries (en.ts, bn.ts) and core.ts are copied from the web app by
// scripts/sync-i18n.mjs, so both apps share every key. English is the default; the choice is kept in
// AsyncStorage and, for signed-in shoppers, saved to the profile (PATCH /me { locale }) so SMS/email
// templates use the same language.
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { api } from "@/api/client";
import { useFeature } from "@/lib/features";
import { setFontLang } from "@/theme/fonts";
import { DEFAULT_LANG, isLang, makeT, type Lang, type Translator } from "./core";

export * from "./core";

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
