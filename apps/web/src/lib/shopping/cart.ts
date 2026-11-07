import { priceBdt, weightKg } from "../pricing";
import type { CartItem, Product } from "../types";

/** CartItem for a product (used by "Add all" / "Add bundle" buttons). */
export function cartLine(p: Product, qty = 1): CartItem {
  return { productId: p.id, market: p.market, title: p.title, image: p.image, placeholder: p.placeholder, unitBdt: priceBdt(p), weightKg: weightKg(p), qty };
}
