export type MarketId = "amazon" | "flipkart" | "bigbasket";
export type StoreMode = MarketId | "all";

export type Variant = {
  id: string | null;
  label: string;
  dimensions: Record<string, string>;
  swatch: string | null;
  selected: boolean;
};

export type Review = {
  author: string;
  rating: number;
  title: string;
  content: string;
  date: string;
};

export type Product = {
  id: string;
  market: MarketId;
  category: string;
  title: string;
  brand: string;
  priceInr: number;
  mrpInr: number;
  rating: number;
  reviews: number;
  image: string | null;
  images: string[];
  placeholder?: { hue: number; icon: string };
  prime: boolean;
  bestSeller: boolean;
  amazonsChoice: boolean;
  salesVolume: string;
  detailed: boolean;
  demo?: boolean;
  stock?: string;
  bullets?: string[];
  specs?: Record<string, string>;
  weight?: string | null;
  variants?: Variant[];
  aplus?: string[];
  hasVideos?: boolean;
  videos?: { hls: string; thumb: string | null }[];
  reviewList?: Review[];
  starBreakdown?: { rating: number; percentage: number }[];
  deliveryKolkata?: string | null;
  categoryPath?: string[];
  // Present on API responses (server-side pricing); the demo computes them with lib/pricing.ts.
  priceBdt?: number;
  mrpBdt?: number;
  fetchedAt?: string;
};

export type CartItem = {
  productId: string;
  market: MarketId;
  title: string;
  image: string | null;
  placeholder?: { hue: number; icon: string };
  variant?: string;
  unitBdt: number;
  weightKg: number;
  qty: number;
};

export type Address = {
  id: string;
  name: string;
  phone: string;
  division: string;
  district: string;
  area: string;
  line: string;
  isDefault?: boolean;
};

export type OrderStatus =
  | "placed"
  | "verified"
  | "purchased"
  | "india_hub"
  | "in_transit"
  | "dhaka_hub"
  | "out_for_delivery"
  | "delivered"
  | "cancelled";

export type Order = {
  id: string;
  createdAt: string;
  items: CartItem[];
  address: Address;
  payment: string;
  subtotal: number;
  shipping: number;
  discount: number;
  total: number;
  status: OrderStatus;
  trackingCode: string;
  paymentStatus?: string;
  timeline?: { status: OrderStatus; at: string }[];
};

export type User = { id?: string; name: string; phone: string; email?: string };
