import type { MarketId, Variant } from "@/lib/types";

export type Media =
  | { kind: "image"; src: string }
  | { kind: "video"; hls: string; thumb: string | null };

// Slim, serialisable product data handed to client components (never the whole catalog).
export type BuyInfo = {
  id: string;
  market: MarketId;
  source: string;
  title: string;
  image: string | null;
  placeholder?: { hue: number; icon: string };
  unitBdt: number;
  mrpBdt: number;
  discount: number;
  weightKg: number;
  variants: Variant[];
  available: boolean;
};
