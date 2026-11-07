import type { MetadataRoute } from "next";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/cart", "/checkout", "/account", "/orders", "/admin", "/api"] }],
    sitemap: "https://retail.skydrop.online/sitemap.xml",
  };
}
