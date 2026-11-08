// Small inline icon set (stroke icons, currentColor) so no icon library is shipped.
const P: Record<string, string> = {
  search: "M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.3-4.3",
  cart: "M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2M10 21h.01M17 21h.01",
  user: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0",
  home: "M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z",
  heart: "M12 20s-7-4.5-9.3-9A5 5 0 0 1 12 6a5 5 0 0 1 9.3 5C19 15.5 12 20 12 20z",
  grid: "M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z",
  globe: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z",
  bolt: "M13 2L4 14h7l-1 8 9-12h-7z",
  spark: "M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z",
  basket: "M5 10h14l-1.5 9a2 2 0 0 1-2 1.7h-7A2 2 0 0 1 6.5 19zM9 10l3-6 3 6M3 10h18",
  truck: "M3 6h11v10H3zM14 9h4l3 3v4h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z",
  pin: "M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z",
  shield: "M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM9 12l2 2 4-4",
  star: "M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z",
  check: "M5 12l5 5 9-11",
  x: "M6 6l12 12M18 6L6 18",
  plus: "M12 5v14M5 12h14",
  minus: "M5 12h14",
  chevron: "M6 9l6 6 6-6",
  right: "M9 6l6 6-6 6",
  left: "M15 6l-6 6 6 6",
  arrow: "M5 12h14M13 6l6 6-6 6",
  phone: "M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM11 18h2",
  shirt: "M8 3l4 2 4-2 5 4-3 3-2-1v12H8V9l-2 1-3-3z",
  shoe: "M3 16c0-3 1-7 3-9l3 3c2 1 5 1 7 2l4 2c1 .5 1 2 1 3v1H3z",
  beauty: "M9 3h6v5H9zM8 8h8l1 13H7zM12 12v5",
  grain: "M12 21V9M12 9c-3 0-5-2-5-5 3 0 5 2 5 5zM12 9c3 0 5-2 5-5-3 0-5 2-5 5zM12 15c-3 0-5-2-5-5 3 0 5 2 5 5zM12 15c3 0 5-2 5-5-3 0-5 2-5 5z",
  juice: "M7 8h10l-1 13H8zM9 8l1-5h4M12 13v4",
  watch: "M9 3h6l1 4H8zM8 17h8l-1 4H9zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zM12 10v2l1.5 1.5",
  audio: "M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v6H5a1 1 0 0 1-1-1zM20 14h-3v6h2a1 1 0 0 0 1-1z",
  bag: "M5 8h14l-1 13H6zM9 8a3 3 0 0 1 6 0",
  oil: "M10 3h4v3h-4zM8 6h8l1 3v11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V9z",
  tea: "M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5zM16 10h2a2 2 0 0 1 0 4h-2M8 3c0 2 2 2 2 4M12 3c0 2 2 2 2 4",
  nut: "M12 3c4 0 7 4 7 8s-3 10-7 10-7-6-7-10 3-8 7-8zM9 9c1 1 5 1 6 0",
  spice: "M8 3h8v4H8zM7 7h10v14H7zM7 12h10",
  noodle: "M4 11h16a8 8 0 0 1-16 0zM7 11c0-4 1-7 3-8M11 11c0-4 1-7 3-8M15 11c0-4 1-7 3-8",
  choco: "M6 3h12v18H6zM6 9h12M6 15h12M12 3v18",
  package: "M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10",
  plane: "M2 16l20-8-8 13-2-6zM12 15l10-7",
  clock: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2",
  wallet: "M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 7l12-4v4M16 13h2",
  card: "M3 6h18v12H3zM3 10h18M7 15h4",
  cash: "M3 7h18v10H3zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z",
  tag: "M3 12V4h8l10 10-8 8zM7.5 7.5h.01",
  filter: "M4 5h16M7 12h10M10 19h4",
  sort: "M7 4v16M4 7l3-3 3 3M17 20V4M14 17l3 3 3-3",
  logout: "M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11",
  sun: "M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4",
  moon: "M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z",
  play: "M7 4l13 8-13 8z",
  share: "M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8.6 13.5l6.8 4M15.4 6.5l-6.8 4",
  trash: "M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13",
  receipt: "M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3",
  help: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01",
  settings: "M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z",
  info: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v6M12 7h.01",
  refresh: "M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4",
  menu: "M4 6h16M4 12h16M4 18h16",
  bell: "M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15zM10 20a2 2 0 0 0 4 0",
  megaphone: "M3 10v4h3l6 4V6L6 10zM15.5 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12",
  verified: "M12 2l2.4 2.2 3.2-.4.9 3.1 2.9 1.5-1 3.1 1 3.1-2.9 1.5-.9 3.1-3.2-.4L12 22l-2.4-2.2-3.2.4-.9-3.1-2.9-1.5 1-3.1-1-3.1 2.9-1.5.9-3.1 3.2.4zM8.5 12l2.5 2.5 4.5-5",
  // category tree icons (apps/api/database/data/category_tree.json)
  laptop: "M5 5h14v10H5zM3 19h18l-2-4H5z",
  tablet: "M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM12 18h.01",
  tv: "M3 6h18v12H3zM8 21h8M12 18v3",
  camera: "M4 8h4l2-3h4l2 3h4v11H4zM12 10a4 4 0 1 0 0 8 4 4 0 0 0 0-8z",
  game: "M6 9h12a4 4 0 0 1 4 4v2a3 3 0 0 1-5.4 1.8L15 15H9l-1.6 1.8A3 3 0 0 1 2 15v-2a4 4 0 0 1 4-4zM7 11v4M5 13h4M16 12h.01M18 14h.01",
  charger: "M7 3v4M11 3v4M5 7h8v4a4 4 0 0 1-8 0zM9 15v2a4 4 0 0 0 8 0V9a2 2 0 0 1 4 0",
  plug: "M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0zM12 17v5",
  wifi: "M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01",
  storage: "M3 13h18v6H3zM5 13l2-8h10l2 8M17 16h.01",
  mouse: "M12 3a6 6 0 0 1 6 6v6a6 6 0 0 1-12 0V9a6 6 0 0 1 6-6zM12 7v4",
  printer: "M7 8V3h10v5M3 16v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6h-4M7 13h10v8H7z",
  dress: "M9 3h6l-1 4 4 14H6l4-14z",
  gem: "M6 3h12l3 6-9 12L3 9zM3 9h18M9 3l3 6 3-6",
  glasses: "M2 14a3 3 0 1 0 6 0 3 3 0 1 0-6 0M16 14a3 3 0 1 0 6 0 3 3 0 1 0-6 0M8 14h8M2 14l2-6M22 14l-2-6",
  luggage: "M7 7h10v13H7zM10 7V4h4v3M10 11v5M14 11v5",
  backpack: "M7 8a5 5 0 0 1 10 0v12H7zM7 14h10M10 6V4h4v2",
  smile: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8.5 14a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01",
  hair: "M4 9h16v3H4zM6 12v7M9 12v7M12 12v7M15 12v7M18 12v7",
  perfume: "M9 3h6v3H9zM7 8h10v13H7zM12 12a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z",
  scissors: "M3 6a3 3 0 1 0 6 0 3 3 0 1 0-6 0M3 18a3 3 0 1 0 6 0 3 3 0 1 0-6 0M8.1 8.1L20 20M8.1 15.9L20 4",
  spray: "M9 9h6v12H9zM10 9V6h4v3M14 6h3M19 3v1M21 5h-1M19 7v1",
  tooth: "M7 3C5 3 3 5 3 8c0 4 2 5 3 9 .5 2 1 4 2 4s1.5-3 2-5c.3-1 .7-2 2-2s1.7 1 2 2c.5 2 1 5 2 5s1.5-2 2-4c1-4 3-5 3-9 0-3-2-5-4-5-2 0-3 1-5 1S9 3 7 3z",
  heartbeat: "M3 12h4l2-5 4 10 2-5h6",
  pill: "M10.5 3.5a5 5 0 0 1 7 7l-7 7a5 5 0 0 1-7-7zM7 10l7 7",
  leaf: "M5 19C5 10 11 4 20 4c0 9-6 15-15 15zM5 19l8-8",
  dumbbell: "M6 7v10M3 9v6M18 7v10M21 9v6M6 12h12",
  pot: "M4 10h16v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM2 10h2M20 10h2M9 6c0-1 1-2 3-2s3 1 3 2",
  frame: "M4 4h16v16H4zM4 15l5-5 4 4 3-3 4 4M15 8h.01",
  bed: "M3 18V6M3 14h18v4M21 14v-1a3 3 0 0 0-3-3h-7v4M7 12a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z",
  sofa: "M4 11V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3M2 13a2 2 0 0 1 4 0v2h12v-2a2 2 0 0 1 4 0v5H2zM5 18v2M19 18v2",
  lamp: "M8 3h8l3 8H5zM12 11v8M8 21h8",
  paw: "M12 13c-3 0-5 3-5 5 0 1.5 1.5 2 2.5 2s1.5-.6 2.5-.6 1.5.6 2.5.6 2.5-.5 2.5-2c0-2-2-5-5-5zM4 9.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0M17 9.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0M7.5 5.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0M13.5 5.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0",
  tool: "M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z",
  fan: "M11 12a1 1 0 1 0 2 0 1 1 0 1 0-2 0M12 11c0-4 1-7 4-7 2 0 2 3-1 5M13 12c4 0 7 1 7 4 0 2-3 2-5-1M12 13c0 4-1 7-4 7-2 0-2-3 1-5M11 12c-4 0-7-1-7-4 0-2 3-2 5 1",
  fridge: "M6 3h12v18H6zM6 10h12M9 6v2M9 13v3",
  milk: "M8 3h8v3l2 3v12H6V9l2-3zM6 12h12",
  baby: "M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zM9.5 11h.01M14.5 11h.01M9.5 14.5c1.4 1.3 3.6 1.3 5 0M12 4c0 1.5 1 2.5 2 2.5",
  ball: "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7l4 3-1.5 4.5h-5L8 10zM12 3v4M21 10.5l-5-.5M3 10.5l5-.5M9.5 14.5L7 19M14.5 14.5L17 19",
  cycle: "M2 17.5a3.5 3.5 0 1 0 7 0 3.5 3.5 0 1 0-7 0M15 17.5a3.5 3.5 0 1 0 7 0 3.5 3.5 0 1 0-7 0M5.5 17.5L9 9h6l3.5 8.5M9 9l3 8.5 3-8.5M8 6h3",
  tent: "M3 20L12 4l9 16zM9 20l3-5 3 5",
  book: "M12 7a3 3 0 0 0-3-3H4v14h5a3 3 0 0 1 3 3M12 7a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3M12 7v14",
  pen: "M15 4l5 5L9 20H4v-5zM13 6l5 5",
  music: "M9 18V5l11-2v13M3 18a3 3 0 1 0 6 0 3 3 0 1 0-6 0M14 16a3 3 0 1 0 6 0 3 3 0 1 0-6 0",
  blocks: "M4 13h7v7H4zM13 13h7v7h-7zM8.5 4h7v7h-7z",
  gift: "M3 9h18v4H3zM5 13h14v8H5zM12 9v12M12 9c-1.5-3-5-4-5-1.5S10 9 12 9zM12 9c1.5-3 5-4 5-1.5S14 9 12 9z",
  car: "M5 11l2-5h10l2 5M3 11h18v6H3zM7 17v2M17 17v2M7 14h.01M17 14h.01",
  bike: "M2 15a3 3 0 1 0 6 0 3 3 0 1 0-6 0M16 15a3 3 0 1 0 6 0 3 3 0 1 0-6 0M5 15h5l3-5h4l2 5M12 10l-2-3H8M15 7h3",
};

export function Icon({
  name,
  size = 20,
  stroke = 1.8,
  className,
  fill = "none",
}: {
  name: string;
  size?: number;
  stroke?: number;
  className?: string;
  fill?: string;
}) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={fill}
      stroke="currentColor"
      strokeWidth={stroke}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      <path d={P[name] ?? P.package} />
    </svg>
  );
}

export function Logo({ className }: { className?: string }) {
  // Original Skydrop Retail mark: a parcel dropping on a parachute line.
  return (
    <span className={"inline-flex items-center gap-2 font-semibold tracking-[-0.01em] text-ink " + (className ?? "")}>
      {/* monochrome mark: ink canopy and lines, parcel in the navy accent */}
      <svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
        <path d="M4 13a12 9 0 0 1 24 0z" fill="currentColor" />
        <path d="M5 13l8.5 8M27 13l-8.5 8M16 13v8" stroke="currentColor" strokeWidth="1.3" />
        <rect x="11" y="20" width="10" height="9" rx="2" fill="var(--accent)" />
      </svg>
      <span className="leading-none">
        Skydrop <span className="font-normal text-ink-2">Retail</span>
      </span>
    </span>
  );
}
