// Amazon CDN images accept a size token before the extension (e.g. ._SS144_.jpg).
// Use it to fetch small thumbnails instead of the 1500px originals.
export function sized(url: string | null | undefined, token: string) {
  if (!url) return url ?? null;
  if (!/m\.media-amazon\.com\/images\/I\//.test(url)) return url;
  return url.replace(/(\._[^/]*_)?\.(jpg|jpeg|png|webp)$/i, `._${token}_.$2`);
}
