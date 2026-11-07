"use client";

import { ShareButton } from "./ShareButton";
import { useShareCodes } from "./useShareCodes";
import { Icon } from "@/components/Icon";

/** Order success: invite friends (invite code, or affiliate ref for approved affiliates). */
export function TellAFriend({ style }: { style?: React.CSSProperties }) {
  const codes = useShareCodes();
  const body = codes.ref
    ? "Share Skydrop Retail with your affiliate link and earn commission on their orders."
    : "When a friend joins with your link and their first order arrives, you both get Skydrop Points.";

  return (
    <section className="co-card rise mt-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center" style={style} aria-labelledby="taf-h">
      <span className="chip-icon is-active h-11 w-11 shrink-0 rounded-2xl">
        <Icon name="spark" size={20} />
      </span>
      <div className="min-w-0 flex-1">
        <h2 id="taf-h" className="text-sm font-extrabold">
          Tell a friend
        </h2>
        <p className="text-xs text-ink-2">{body}</p>
      </div>
      <ShareButton
        variant="ghost"
        label="Invite friends"
        path="/"
        title="Skydrop Retail"
        text="I shop amazon.in, Flipkart and BigBasket from Bangladesh with Skydrop Retail. Prices in taka, delivered home:"
        qr
      />
    </section>
  );
}
