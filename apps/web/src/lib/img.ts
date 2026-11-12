// Right-sized product photos. The stores' own image servers resize on request, so a 180 px card never downloads a
// 1500 px photo (a 225 KB Myntra photo is 25 KB at card size). Unknown hosts are returned unchanged.
// Shared with the app: apps/mobile/scripts/sync-i18n.mjs copies this file to apps/mobile/src/lib/img.ts.

/** Widths we ask for, so the same few sizes are cached by the browser and the CDNs. */
const STEPS = [96, 160, 240, 360, 480, 720, 1080, 1500];
const step = (w: number) => STEPS.find((s) => s >= w) ?? STEPS[STEPS.length - 1];

/** `src` resized to about `width` real pixels (rounded up to a standard step). */
export function sizedImg(src: string | null | undefined, width: number): string {
  if (!src) return "";
  const w = step(Math.round(width));
  try {
    // Amazon: https://m.media-amazon.com/images/I/61o-LEEWKTL._SL1196_.jpg  →  …/61o-LEEWKTL._SL480_QL75_.jpg
    if (/\/\/(m\.media-amazon\.com|images-[a-z-]+\.ssl-images-amazon\.com)\/images\/I\//.test(src)) {
      return src.replace(/(\/images\/I\/[^./]+)(?:\.[^/]*)?\.(jpe?g|png|webp)(\?.*)?$/i, `$1._SL${w}_QL75_.$2`);
    }
    // Flipkart: https://rukminim1.flixcart.com/image/1500/1500/xif0q/…jpeg?q=70  (the two numbers are a bounding box)
    if (/\/\/rukmini[a-z0-9]*\.flixcart\.com\/image\/\d+\/\d+\//.test(src)) {
      return src.replace(/\/image\/\d+\/\d+\//, `/image/${w}/${Math.round(w * 1.25)}/`).replace(/([?&])q=\d+/, "$1q=70");
    }
    // Myntra: https://assets.myntassets.com/[transform/]assets/images/…  →  …/dpr_1,q_60,w_480,c_limit,fl_progressive/assets/images/…
    if (/\/\/assets\.myntassets\.com\//.test(src)) {
      const t = `f_auto,dpr_1,q_60,w_${w},c_limit,fl_progressive`;
      return src.replace(/(\/\/assets\.myntassets\.com\/)(?:[a-z]+_[^/]*,[^/]*\/|[a-z]_[^/]+\/)?(?:v1\/)?(assets\/)/i, `$1${t}/$2`);
    }
  } catch {
    /* fall through */
  }
  return src;
}

/** True when we know how to resize this photo. */
export const canResize = (src: string | null | undefined) => !!src && sizedImg(src, 96) !== src;

/**
 * `src` + `srcSet` for a photo shown about `cssWidth` CSS pixels wide: a 1x and a 2x candidate (phones with 3x
 * screens take the 2x one, which is visually the same at these sizes and a third lighter).
 */
export function imgSet(src: string | null | undefined, cssWidth: number): { src: string; srcSet?: string } {
  if (!src) return { src: "" };
  if (!canResize(src)) return { src };
  const one = sizedImg(src, cssWidth);
  const two = sizedImg(src, cssWidth * 2);
  return one === two ? { src: one } : { src: two, srcSet: `${one} 1x, ${two} 2x` };
}

/** `srcSet` with width descriptors for a big photo (product gallery): pair it with a `sizes` attribute. */
export function imgWidths(src: string | null | undefined, widths: number[] = [480, 720, 1080]): string | undefined {
  if (!src || !canResize(src)) return undefined;
  return widths.map((w) => `${sizedImg(src, w)} ${step(w)}w`).join(", ");
}
