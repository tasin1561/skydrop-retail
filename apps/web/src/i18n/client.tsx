"use client";

// Client components: `const { t, bdt } = useT()`. The server layout passes the cookie language in,
// so the first client render matches the server HTML.
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, useTransition, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { LANG_COOKIE, makeT, type Lang, type Translator } from "./core";

type Ctx = { lang: Lang; setLang: (l: Lang) => void; switching: boolean };
const LangCtx = createContext<Ctx>({ lang: "en", setLang: () => {}, switching: false });

const reduceMotion = () => typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function LangProvider({ initial, children }: { initial: Lang; children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(initial);
  const [pending, startTransition] = useTransition();
  const [fading, setFading] = useState(false);
  const router = useRouter();
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  // Server re-render finished: fade the page back in.
  useEffect(() => {
    if (pending || !fading) return;
    const id = requestAnimationFrame(() => {
      document.documentElement.classList.remove("lang-fade");
      setFading(false);
    });
    return () => cancelAnimationFrame(id);
  }, [pending, fading]);

  useEffect(() => () => clearTimeout(timer.current), []);

  const setLang = useCallback(
    (l: Lang) => {
      if (l === lang) return;
      try {
        document.cookie = `${LANG_COOKIE}=${l}; path=/; max-age=31536000; samesite=lax`;
      } catch {
        /* cookies blocked: the switch still works for this page view */
      }
      const swap = () => {
        document.documentElement.lang = l;
        startTransition(() => {
          setLangState(l);
          router.refresh(); // server components read the cookie again
        });
      };
      if (reduceMotion()) return swap();
      // Crossfade: fade out (opacity only), swap the words, fade back in when the refresh lands.
      setFading(true);
      document.documentElement.classList.add("lang-fade");
      clearTimeout(timer.current);
      timer.current = setTimeout(swap, 140);
    },
    [lang, router],
  );

  const value = useMemo(() => ({ lang, setLang, switching: pending || fading }), [lang, setLang, pending, fading]);
  return <LangCtx.Provider value={value}>{children}</LangCtx.Provider>;
}

export function useLang() {
  return useContext(LangCtx);
}

export function useT(): Translator {
  return makeT(useContext(LangCtx).lang);
}
