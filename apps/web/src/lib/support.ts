// Support contact details from env (NEXT_PUBLIC_SUPPORT_*). Placeholders are shown until they are set.
// NEXT_PUBLIC_SUPPORT_WHATSAPP   8801XXXXXXXXX (digits, country code first)
// NEXT_PUBLIC_SUPPORT_PHONE      +8809XXXXXXXXX or 01XXXXXXXXX
// NEXT_PUBLIC_SUPPORT_EMAIL      support@example.com
// NEXT_PUBLIC_SUPPORT_MESSENGER  Facebook page username (m.me/<name>)
// NEXT_PUBLIC_SUPPORT_HOURS      e.g. "Sat-Thu, 10am-8pm"
// NEXT_PUBLIC_SUPPORT_ADDRESS    registered office address

import type { MsgKey, TFn } from "@/i18n/core";

const env = {
  whatsapp: process.env.NEXT_PUBLIC_SUPPORT_WHATSAPP?.replace(/\D/g, "") ?? "",
  phone: process.env.NEXT_PUBLIC_SUPPORT_PHONE?.trim() ?? "",
  email: process.env.NEXT_PUBLIC_SUPPORT_EMAIL?.trim() ?? "",
  messenger: process.env.NEXT_PUBLIC_SUPPORT_MESSENGER?.trim().replace(/^@/, "") ?? "",
  hours: process.env.NEXT_PUBLIC_SUPPORT_HOURS?.trim() ?? "",
  address: process.env.NEXT_PUBLIC_SUPPORT_ADDRESS?.trim() ?? "",
};

export type Channel = { id: "whatsapp" | "messenger" | "phone" | "email"; label: string; value: string; href: string | null; icon: string; note: string };

export const SUPPORT = {
  hours: env.hours || "Saturday to Thursday, 10am to 8pm (Dhaka time)",
  address: env.address || "[Registered office address, Dhaka]",
  configured: Boolean(env.whatsapp || env.messenger || env.phone || env.email),
  whatsappHref: env.whatsapp ? `https://wa.me/${env.whatsapp}` : null,
  messengerHref: env.messenger ? `https://m.me/${encodeURIComponent(env.messenger)}` : null,
  channels: [
    {
      id: "whatsapp",
      label: "WhatsApp",
      value: env.whatsapp ? `+${env.whatsapp}` : "+880 1XXX-XXXXXX",
      href: env.whatsapp ? `https://wa.me/${env.whatsapp}` : null,
      icon: "phone",
      note: "Fastest. Send your order number (SR-…) and we reply in working hours.",
    },
    {
      id: "messenger",
      label: "Messenger",
      value: env.messenger ? `m.me/${env.messenger}` : "m.me/[page]",
      href: env.messenger ? `https://m.me/${encodeURIComponent(env.messenger)}` : null,
      icon: "share",
      note: "Message our Facebook page.",
    },
    {
      id: "phone",
      label: "Phone",
      value: env.phone || "+880 9XXX-XXXXXX",
      href: env.phone ? `tel:${env.phone.replace(/[^\d+]/g, "")}` : null,
      icon: "phone",
      note: "Call during support hours.",
    },
    {
      id: "email",
      label: "Email",
      value: env.email || "support@[your-domain]",
      href: env.email ? `mailto:${env.email}` : null,
      icon: "receipt",
      note: "For refunds, invoices and anything that needs attachments.",
    },
  ] as Channel[],
};

/** Support hours and channel notes in the shopper's language (env values are shown as set). */
export function localSupport(t: TFn) {
  return {
    hours: env.hours || t("support.hours"),
    channels: SUPPORT.channels.map((c) => ({
      ...c,
      label: c.id === "phone" ? t("support.phoneLabel") : c.id === "email" ? t("support.emailLabel") : c.label,
      note: t(`support.${c.id}` as MsgKey),
    })),
  };
}
