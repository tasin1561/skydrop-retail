import { reducedMotion } from "./flyToCart";

/**
 * FLIP "grow from thumbnail": a ghost copy of the tapped image scales from where it sits to the
 * centre of the screen while the viewer opens underneath, then fades away. Transform + opacity only.
 */
export function flipOpen(from: Element | null | undefined, src: string | null | undefined) {
  if (!from || !src || reducedMotion()) return;
  const r = from.getBoundingClientRect();
  if (!r.width || !r.height) return;
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const size = Math.min(vw - 32, vh * 0.72, 720);
  const tx = (vw - size) / 2;
  const ty = (vh - size) / 2;
  const s0 = Math.min(r.width, r.height) / size;
  const x0 = r.left + (r.width - size * s0) / 2;
  const y0 = r.top + (r.height - size * s0) / 2;

  const ghost = document.createElement("img");
  ghost.src = src;
  ghost.alt = "";
  ghost.className = "mo-ghost";
  ghost.setAttribute("aria-hidden", "true");
  ghost.style.width = ghost.style.height = `${size}px`;
  ghost.style.transform = `translate(${x0}px, ${y0}px) scale(${s0})`;
  document.body.appendChild(ghost);
  void ghost.offsetWidth;
  ghost.style.transition = "transform 0.38s var(--ease), opacity 0.2s var(--ease) 0.34s";
  ghost.style.transform = `translate(${tx}px, ${ty}px) scale(1)`;
  ghost.style.opacity = "0";
  setTimeout(() => ghost.remove(), 700);
}
