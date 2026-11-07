"use client";

import { useEffect, useState } from "react";
import { useStore } from "@/state/store";
import { loadShareCodes, onShareCodesChange, type ShareCodes } from "@/lib/growth";

/** { ref } for an approved affiliate, otherwise { invite } when signed in, else {}. */
export function useShareCodes(): ShareCodes {
  const { user, token, ready } = useStore();
  const [codes, setCodes] = useState<ShareCodes>({});
  const [bump, setBump] = useState(0);
  const phone = user?.phone ?? null;

  useEffect(() => onShareCodesChange(() => setBump((n) => n + 1)), []);

  useEffect(() => {
    if (!ready) return;
    let alive = true;
    loadShareCodes(phone ? { phone } : null, token).then((c) => {
      if (alive) setCodes(c);
    });
    return () => {
      alive = false;
    };
  }, [ready, phone, token, bump]);

  return codes;
}
