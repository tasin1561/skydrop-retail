import type { Metadata } from "next";
import { WishlistGrid } from "@/components/account/WishlistGrid";

export const metadata: Metadata = { title: "Wishlist" };

export default function WishlistPage() {
  return <WishlistGrid />;
}
