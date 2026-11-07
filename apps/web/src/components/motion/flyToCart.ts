// Add-to-cart feedback: one short bump on the visible cart icon (no flying parcel).
// The real cart change is made by the caller; this is decoration that never blocks anything.

let flyingUntil = 0;

export const isFlying = () => Date.now() < flyingUntil;

export function reducedMotion() {
  return typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
}

/** Restartable bump on every visible cart icon + its badge. */
export function bumpCart() {
  document.querySelectorAll<HTMLElement>("[data-cart-target]").forEach((el) => {
    el.classList.remove("mo-bump");
    void el.offsetWidth; // restart the keyframe
    el.classList.add("mo-bump");
    el.addEventListener("animationend", () => el.classList.remove("mo-bump"), { once: true });
  });
}

/**
 * Add-to-cart acknowledgement. The flying parcel is retired (calm, corporate motion): the item lands
 * immediately; MotionRuntime gives the visible cart icon one short bump as the badge updates. Signature kept so
 * callers can still time a "done" state; it always returns 0 (no flight).
 */
export function flyToCart(from: Element | null, image?: string | null, onLand?: () => void): number {
  void from;
  void image;
  flyingUntil = 0;
  onLand?.(); // the cart count goes up -> MotionRuntime bumps the cart icon once
  return 0;
}
