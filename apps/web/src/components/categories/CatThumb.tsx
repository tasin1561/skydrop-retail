import { Icon } from "../Icon";
import { FxImg } from "../motion/FxImg";

type Thumb = { icon: string; image?: string | null; images?: string[] | null };

/** Amazon photos are product shots on white: show them whole. Myntra/Flipkart photos are shot on models: fill the tile. */
const fit = (src: string) =>
  /(^|\.)media-amazon\.com\/|images-amazon\.com\//.test(src) ? "h-full w-full object-contain p-[9%]" : "h-full w-full object-cover object-top";

/**
 * A category's picture, the way Myntra, Flipkart and MoveOn show categories: a real product photo (the admin's image
 * or the best product of that category, see the API's CategoryCovers), or a 2x2 collage of four for big tiles. A
 * category with no products yet gets its own icon (saree, heels, lipstick...) on a soft tint instead.
 *
 * Motion lives in categories.css: the photo zooms a little and the icon lifts when the surrounding link is hovered,
 * focused or pressed (transform only, off with reduced motion).
 */
export function CatThumb({
  node,
  className = "h-14 w-14 rounded-2xl",
  iconSize = 24,
  collage = false,
  eager = false,
}: {
  node: Thumb;
  className?: string;
  iconSize?: number;
  collage?: boolean;
  eager?: boolean;
}) {
  const images = (node.images?.length ? node.images : node.image ? [node.image] : []).filter(Boolean);
  const loading = eager ? "eager" : "lazy";
  if (collage && images.length >= 4) {
    return (
      <span aria-hidden className={"cat-thumb grid grid-cols-2 gap-px " + className} data-photo="true">
        {images.slice(0, 4).map((src) => (
          <FxImg key={src} src={src} alt="" loading={loading} decoding="async" className={fit(src)} />
        ))}
      </span>
    );
  }
  return (
    <span aria-hidden className={"cat-thumb " + className} data-photo={images.length ? "true" : undefined}>
      {images.length ? (
        <FxImg src={images[0]} alt="" loading={loading} decoding="async" className={fit(images[0])} />
      ) : (
        <Icon name={node.icon} size={iconSize} stroke={1.6} />
      )}
    </span>
  );
}
