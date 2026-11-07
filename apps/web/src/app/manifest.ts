import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Skydrop Retail",
    short_name: "Skydrop Retail",
    description: "Shop Indian stores from Bangladesh, delivered to your door by Skydrop.",
    start_url: "/",
    display: "standalone",
    background_color: "#f6f7fb",
    theme_color: "#0b6bcb",
    icons: [{ src: "/icon.svg", sizes: "any", type: "image/svg+xml" }],
  };
}
