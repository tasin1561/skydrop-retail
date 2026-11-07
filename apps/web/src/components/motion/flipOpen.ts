/**
 * Gallery "grow from thumbnail" FLIP is retired: the viewer simply fades open (product.css).
 * Kept as a no-op so callers need no change.
 */
export function flipOpen(from: Element | null | undefined, src: string | null | undefined): void {
  void from;
  void src;
}
