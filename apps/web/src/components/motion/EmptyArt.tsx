// Friendly inline-SVG illustrations for empty states. Original Skydrop art: theme colours via CSS
// variables and currentColor, gentle loops that pause off-screen ([data-loop]) and stop under
// reduced motion. Decorative only (aria-hidden); the heading next to it carries the meaning.

export type EmptyKind = "wishlist" | "orders" | "search" | "parcel";

export function EmptyArt({ kind, className }: { kind: EmptyKind; className?: string }) {
  return (
    <svg
      data-loop=""
      suppressHydrationWarning
      viewBox="0 0 160 128"
      width="160"
      height="128"
      aria-hidden="true"
      className={"overflow-visible text-accent " + (className ?? "")}
    >
      <circle cx="80" cy="62" r="52" fill="currentColor" opacity="0.08" />
      <circle cx="80" cy="62" r="36" fill="currentColor" opacity="0.06" />
      <path className="ea-twinkle" d="M24 28l2 5 5 2-5 2-2 5-2-5-5-2 5-2z" fill="currentColor" opacity=".6" />
      <path
        className="ea-twinkle"
        style={{ animationDelay: "1.1s" }}
        d="M136 38l1.5 3.5 3.5 1.5-3.5 1.5-1.5 3.5-1.5-3.5-3.5-1.5 3.5-1.5z"
        fill="var(--brand-2)"
      />
      <circle className="ea-twinkle" style={{ animationDelay: "0.5s" }} cx="132" cy="92" r="2.5" fill="currentColor" opacity=".5" />
      <ellipse className="ea-shadow" cx="80" cy="116" rx="28" ry="4" fill="var(--ink-3)" />
      {kind === "wishlist" && <Wishlist />}
      {kind === "orders" && <Orders />}
      {kind === "search" && <Search />}
      {kind === "parcel" && <Parcel />}
    </svg>
  );
}

/* a parcel floating on a heart-shaped balloon */
function Wishlist() {
  return (
    <g className="ea-bob" style={{ transformOrigin: "80px 40px" }}>
      <g className="ea-beat" >
        <path
          d="M80 54c-14-9-24-17-24-28 0-7 5-12 11-12 5 0 10 3 13 8 3-5 8-8 13-8 6 0 11 5 11 12 0 11-10 19-24 28z"
          fill="rgb(244 63 94)"
        />
        <path d="M66 22c2-3 5-4 8-3" stroke="#fff" strokeOpacity=".7" strokeWidth="3" strokeLinecap="round" fill="none" />
      </g>
      <path d="M80 54v28" stroke="var(--ink-3)" strokeWidth="1.5" />
      <rect x="64" y="82" width="32" height="26" rx="6" fill="var(--brand-2)" />
      <path d="M64 92h32M80 82v10" stroke="#fff" strokeOpacity=".55" strokeWidth="2" />
    </g>
  );
}

/* a receipt with a little delivery van driving across it */
function Orders() {
  return (
    <>
      <g className="ea-bob" style={{ transformOrigin: "80px 60px" }}>
        <path d="M54 18h52v84l-6.5-5-6.5 5-6.5-5-6.5 5-6.5-5-6.5 5-6.5-5L54 102z" fill="var(--surface)" stroke="currentColor" strokeOpacity=".35" strokeWidth="1.5" />
        <rect x="63" y="30" width="34" height="6" rx="3" fill="currentColor" opacity=".8" />
        <rect x="63" y="44" width="26" height="4" rx="2" fill="var(--ink-3)" opacity=".6" />
        <rect x="63" y="54" width="30" height="4" rx="2" fill="var(--ink-3)" opacity=".6" />
        <rect x="63" y="64" width="20" height="4" rx="2" fill="var(--ink-3)" opacity=".6" />
        <path d="M63 80h34" stroke="var(--line)" strokeWidth="2" strokeDasharray="3 3" />
      </g>
      <g className="ea-drive">
        <g transform="translate(64 96)">
          <path d="M0 0h20v12H0z" fill="var(--brand-2)" />
          <path d="M20 4h6l4 5v3H20z" fill="var(--brand-2)" />
          <path d="M22 5.5h3.4l2.4 3H22z" fill="#fff" opacity=".7" />
          <circle cx="6" cy="13" r="2.8" fill="var(--ink)" />
          <circle cx="24" cy="13" r="2.8" fill="var(--ink)" />
        </g>
      </g>
    </>
  );
}

/* a magnifier sweeping over a parcel that peeks out */
function Search() {
  return (
    <>
      <g transform="translate(62 70)">
        <rect width="36" height="32" rx="6" fill="var(--brand-2)" />
        <path d="M0 11h36M18 0v11" stroke="#fff" strokeOpacity=".55" strokeWidth="2" />
      </g>
      <g className="ea-scan" style={{ transformOrigin: "80px 52px" }}>
        <circle cx="76" cy="46" r="20" fill="var(--surface)" fillOpacity=".7" stroke="currentColor" strokeWidth="6" />
        <path d="M90 60l14 14" stroke="currentColor" strokeWidth="8" strokeLinecap="round" />
        <path d="M66 40a12 12 0 0 1 8-6" stroke="#fff" strokeWidth="3" strokeLinecap="round" fill="none" opacity=".9" />
        <circle cx="71" cy="47" r="2" fill="var(--ink)" />
        <circle cx="81" cy="47" r="2" fill="var(--ink)" />
        <path d="M72 54q4 3 8 0" stroke="var(--ink)" strokeWidth="1.8" strokeLinecap="round" fill="none" />
      </g>
    </>
  );
}

/* the Skydrop parcel under a parachute */
function Parcel() {
  return (
    <g className="ea-bob" style={{ transformOrigin: "80px 30px" }}>
      <path d="M50 38a30 22 0 0 1 60 0z" fill="currentColor" opacity=".9" />
      <path d="M66 38a14 22 0 0 1 28 0" fill="var(--accent-2)" opacity=".55" />
      <path d="M50 38l20 34M110 38L90 72M80 38v34" stroke="var(--ink-3)" strokeWidth="1.4" />
      <rect x="64" y="70" width="32" height="28" rx="6" fill="var(--brand-2)" />
      <path d="M64 80h32M80 70v10" stroke="#fff" strokeOpacity=".55" strokeWidth="2" />
    </g>
  );
}
