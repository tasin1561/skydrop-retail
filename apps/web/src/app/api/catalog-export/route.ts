import { NextResponse } from "next/server";
import { internalHeaders } from "@/lib/data";

// Honeypot. Only bots follow the hidden "catalog export" link (robots.txt disallows /api). The API blocks the
// caller's IP for 24 h and logs it; we pass the shopper IP along because the API sees the Next.js server.
export async function GET(req: Request) {
  const api = process.env.API_URL?.replace(/\/$/, "");
  const ip = req.headers.get("cf-connecting-ip") ?? req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ?? "";
  if (api && ip) {
    await fetch(api + "/products/export", { headers: { Accept: "application/json", ...internalHeaders, "X-Client-IP": ip }, cache: "no-store" }).catch(() => null);
  }
  return NextResponse.json({ message: "Not found." }, { status: 404 });
}
