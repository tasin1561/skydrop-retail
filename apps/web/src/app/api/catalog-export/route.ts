import { NextResponse } from "next/server";
import { internalHeaders } from "@/lib/data";
import { clientIp } from "@/lib/clientIp";

// Honeypot. Only bots follow the hidden "catalog export" link (robots.txt disallows /api). The API blocks the
// caller's IP for 24 h and logs it; we pass the shopper IP along because the API sees the Next.js server.
export async function GET(req: Request) {
  const api = process.env.API_URL?.replace(/\/$/, "");
  const ip = clientIp(req.headers);
  // Only a crawler following the hidden link counts. A browser loading this URL from another site (an <img>, a link
  // in a forum post) sends Sec-Fetch-Site: cross-site, and blocking that shopper's IP would let anyone lock real
  // customers out for a day. Plain HTTP clients send no fetch metadata; headless browsers crawling us send same-origin.
  const site = req.headers.get("sec-fetch-site");
  const dest = req.headers.get("sec-fetch-dest");
  const crawler = !site || (site === "same-origin" && (!dest || dest === "document"));
  if (api && ip && crawler) {
    await fetch(api + "/products/export", { headers: { Accept: "application/json", ...internalHeaders, "X-Client-IP": ip }, cache: "no-store" }).catch(() => null);
  }
  return NextResponse.json({ message: "Not found." }, { status: 404 });
}
