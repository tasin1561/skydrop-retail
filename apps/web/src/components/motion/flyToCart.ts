// Fly-to-cart: a small product thumbnail (or a Skydrop parcel) arcs from the button to the visible
// cart icon. FLIP with transform only: the outer layer moves on X, the inner on Y, which draws an arc.
// The real cart change is made by the caller; this is decoration that never blocks anything.

let flyingUntil = 0;

export const isFlying = () => Date.now() < flyingUntil;

export function reducedMotion() {
  return typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
}

/** The cart icon the shopper can see right now (header pill on desktop, bottom tab on phones). */
function cartTarget(): HTMLElement | null {
  const all = Array.from(document.querySelectorAll<HTMLElement>("[data-cart-target]"));
  return all.find((el) => el.offsetParent !== null && el.getBoundingClientRect().width > 0) ?? null;
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

const PARCEL =
  '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>';

/**
 * Launch the parcel. Returns the flight time in ms (0 when skipped) so callers can time the
 * "done" state to the landing. Calls onLand when it arrives (also when skipped, immediately).
 */
export function flyToCart(from: Element | null, image?: string | null, onLand?: () => void): number {
  const target = cartTarget();
  if (!from || !target || reducedMotion()) {
    onLand?.();
    bumpCart();
    return 0;
  }
  const a = from.getBoundingClientRect();
  const b = target.getBoundingClientRect();
  const size = 52;
  const x0 = a.left + a.width / 2 - size / 2;
  const y0 = a.top + a.height / 2 - size / 2;
  const x1 = b.left + b.width / 2 - size / 2;
  const y1 = b.top + b.height / 2 - size / 2;
  const dist = Math.hypot(x1 - x0, y1 - y0);
  const dur = Math.round(Math.min(820, Math.max(560, dist * 0.9)));

  const outer = document.createElement("div");
  outer.className = "mo-fly";
  outer.setAttribute("aria-hidden", "true");
  outer.style.setProperty("--x0", `${x0}px`);
  outer.style.setProperty("--x1", `${x1}px`);
  outer.style.setProperty("--y0", `${y0}px`);
  outer.style.setProperty("--y1", `${y1}px`);
  outer.style.setProperty("--lift", `${Math.min(110, 40 + dist * 0.12)}px`);
  outer.style.setProperty("--fly-d", `${dur}ms`);
  const inner = document.createElement("i");
  if (image) {
    const img = document.createElement("img");
    img.src = image;
    img.alt = "";
    img.decoding = "async";
    inner.appendChild(img);
  } else {
    inner.innerHTML = PARCEL;
  }
  outer.appendChild(inner);
  document.body.appendChild(outer);

  flyingUntil = Date.now() + dur + 50;
  const done = () => {
    outer.remove();
    onLand?.();
    bumpCart();
  };
  // animationend on the Y layer marks the landing; the timeout is a safety net (tab hidden, etc.).
  let landed = false;
  const land = () => {
    if (landed) return;
    landed = true;
    done();
  };
  inner.addEventListener("animationend", land, { once: true });
  setTimeout(land, dur + 120);
  return dur;
}
