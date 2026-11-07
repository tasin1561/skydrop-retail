import type { Metadata } from "next";
import { InviteView } from "@/components/growth/InviteView";

export const metadata: Metadata = { title: "Invite friends", robots: { index: false } };

export default function InvitePage() {
  return <InviteView />;
}
