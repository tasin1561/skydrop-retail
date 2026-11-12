"use client";

import { useEffect, useRef } from "react";
import { imgSet, sizedImg } from "@/lib/img";

/**
 * <img> that fades and settles in once decoded (the blur-up stand-in: opacity + scale, no filters).
 * Images already loaded before hydration are left alone, so server HTML never hides a picture.
 * Product photos from the stores are requested at the size they are shown (`w`), never at full size.
 */
export function FxImg({ w = 240, ...props }: React.ImgHTMLAttributes<HTMLImageElement> & { /** shown width in CSS px (default 240: a card) */ w?: number }) {
  // Store photos are asked for at the size they are shown (lib/img.ts), with a 2x candidate for sharp screens.
  if (typeof props.src === "string" && !props.srcSet) props = { ...props, ...imgSet(props.src, w) };
  const ref = useRef<HTMLImageElement>(null);
  useEffect(() => {
    const img = ref.current;
    if (!img || (img.complete && img.naturalWidth > 0)) return;
    img.dataset.state = "loading";
    const done = () => {
      img.dataset.state = "loaded";
    };
    img.addEventListener("load", done, { once: true });
    img.addEventListener("error", done, { once: true });
    return () => {
      img.removeEventListener("load", done);
      img.removeEventListener("error", done);
    };
  }, [props.src]);
  // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
  return <img ref={ref} data-fx="" {...props} />;
}

/**
 * Desktop hover swap to the second product photo. Nothing loads until the pointer first enters
 * the card (closest .group), and the photo only shows once it has loaded.
 */
export function HoverImage({ src }: { src: string }) {
  const ref = useRef<HTMLSpanElement>(null);
  useEffect(() => {
    const anchor = ref.current;
    const card = anchor?.closest(".group");
    if (!anchor || !card || !window.matchMedia("(hover: hover)").matches) return;
    const enter = () => {
      if (anchor.firstChild) return;
      const img = document.createElement("img");
      img.className = "mo-img2";
      img.alt = "";
      img.decoding = "async";
      img.onload = () => (img.dataset.ready = "true");
      img.src = sizedImg(src, 480);
      anchor.appendChild(img);
    };
    card.addEventListener("pointerenter", enter, { once: true });
    return () => card.removeEventListener("pointerenter", enter);
  }, [src]);
  return <span ref={ref} aria-hidden="true" className="contents" />;
}
