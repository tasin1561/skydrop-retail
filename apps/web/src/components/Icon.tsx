import { CATEGORY_ICONS } from "@/lib/categoryIcons";

// Small inline icon set (stroke icons, currentColor) so no icon library is shipped. Category icons live in
// lib/categoryIcons.ts (shared with the app).
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
  trend: "M3 17l6-6 4 4 8-8M15 7h6v6",
  link: "M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1",
  ruler: "M3 16L16 3l5 5L8 21zM7 12l2 2M10 9l2 2M13 6l2 2",
  calc: "M6 3h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01",
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
      <path d={P[name] ?? CATEGORY_ICONS[name] ?? P.package} />
    </svg>
  );
}

export function Logo({ className }: { className?: string }) {
  // Original Skydrop Retail mark: a parcel dropping on a parachute line.
  return (
    <span className={"inline-flex items-center gap-2 font-extrabold tracking-tight " + (className ?? "")}>
      <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
        <defs>
          <linearGradient id="lg-sr" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#0f766e" />
            <stop offset="1" stopColor="#5eead4" />
          </linearGradient>
        </defs>
        <path d="M4 13a12 9 0 0 1 24 0z" fill="url(#lg-sr)" />
        <path d="M5 13l8.5 8M27 13l-8.5 8M16 13v8" stroke="#0f766e" strokeWidth="1.3" />
        <rect x="11" y="20" width="10" height="9" rx="2.2" fill="#e8552f" />
        <path d="M11 23.5h10" stroke="#fff" strokeOpacity=".6" />
      </svg>
      <span className="leading-none">
        Skydrop<span className="text-brand-2"> Retail</span>
      </span>
    </span>
  );
}
