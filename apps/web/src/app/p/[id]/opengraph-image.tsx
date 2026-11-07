import { ImageResponse } from "next/og";
import { getProduct } from "@/lib/data";
import { STORES } from "@/lib/markets";
import { priceBdt } from "@/lib/pricing";

// Store chip in our own per-store gradient (same text-safe stops as StoreName); no third-party colours.
const OG_FILL: Record<string, string> = {
  amazon: "linear-gradient(100deg, #5b3df5, #9426c4)",
  flipkart: "linear-gradient(100deg, #c8185f, #b8361a)",
  bigbasket: "linear-gradient(100deg, #0a7a55, #0b7287)",
};

// Per-product social card: product image, title, BDT price, origin store chip and Skydrop Retail branding.
export const alt = "Product on Skydrop Retail";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";
export const revalidate = 3600;

/** Satori can't fail on a slow or unsupported remote image, so fetch it ourselves (jpeg/png/gif only). */
async function imageData(url: string | null | undefined): Promise<string | null> {
  if (!url || !/^https?:\/\//.test(url)) return null;
  // Product images come from scraped listings: never let one point this server at itself or the private network.
  try {
    const h = new URL(url).hostname.replace(/^\[|\]$/g, "").toLowerCase();
    if (h === "localhost" || /\.(localhost|local|internal|lan)$/.test(h) || /^[\d.]+$/.test(h) || h.includes(":")) return null;
  } catch {
    return null;
  }
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(4000), next: { revalidate: 86_400 } });
    const type = res.headers.get("content-type") ?? "";
    if (!res.ok || !/image\/(jpeg|jpg|png|gif)/.test(type)) return null;
    const buf = Buffer.from(await res.arrayBuffer());
    if (buf.byteLength > 4_000_000) return null;
    return `data:${type.split(";")[0]};base64,${buf.toString("base64")}`;
  } catch {
    return null;
  }
}

const bdt = (n: number) => "BDT " + Math.round(n).toLocaleString("en-US"); // the taka sign is not in the default OG font

export default async function Image({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const p = await getProduct(decodeURIComponent(id));
  const brand = (
    <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
      <div style={{ width: 46, height: 46, borderRadius: 14, background: "#ff4d2e", display: "flex", alignItems: "center", justifyContent: "center", color: "#fff", fontSize: 28, fontWeight: 800 }}>
        S
      </div>
      <div style={{ display: "flex", fontSize: 30, fontWeight: 800, color: "#0b0b0f" }}>
        Skydrop<span style={{ color: "#ff4d2e", marginLeft: 8 }}>Retail</span>
      </div>
    </div>
  );

  if (!p) {
    return new ImageResponse(
      (
        <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", justifyContent: "center", padding: 80, background: "#f7f7f4", gap: 24 }}>
          {brand}
          <div style={{ fontSize: 56, fontWeight: 800, color: "#0b0b0f" }}>Shop Indian stores from Bangladesh</div>
        </div>
      ),
      size,
    );
  }

  const img = await imageData(p.image);
  const store = STORES[p.market];
  const price = priceBdt(p);
  const title = p.title.length > 110 ? p.title.slice(0, 107).trimEnd() + "..." : p.title;
  const hue = p.placeholder?.hue ?? 210;

  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", background: "linear-gradient(135deg, #f7f7f4 0%, #ece8fe 100%)", padding: 48, gap: 48 }}>
        <div
          style={{
            width: 534,
            height: 534,
            borderRadius: 36,
            background: img ? "#ffffff" : `hsl(${hue} 70% 92%)`,
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            boxShadow: "0 24px 60px rgba(11,16,32,0.12)",
            overflow: "hidden",
          }}
        >
          {img ? (
            // eslint-disable-next-line jsx-a11y/alt-text
            <img src={img} width={470} height={470} style={{ objectFit: "contain" }} />
          ) : (
            <div style={{ fontSize: 160, color: `hsl(${hue} 60% 45%)`, fontWeight: 800 }}>{(p.brand || p.title).slice(0, 1).toUpperCase()}</div>
          )}
        </div>
        <div style={{ flex: 1, display: "flex", flexDirection: "column", justifyContent: "space-between", paddingTop: 8, paddingBottom: 8 }}>
          {brand}
          <div style={{ display: "flex", flexDirection: "column", gap: 18 }}>
            <div style={{ display: "flex" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 18px", borderRadius: 999, background: OG_FILL[p.market], color: "#fff", fontSize: 22, fontWeight: 700 }}>
                {store.name} · from {store.source}
              </div>
            </div>
            <div style={{ fontSize: title.length > 70 ? 38 : 46, fontWeight: 800, color: "#0b0b0f", lineHeight: 1.15 }}>{title}</div>
            {p.brand ? <div style={{ fontSize: 24, color: "#55555f", fontWeight: 600 }}>{p.brand}</div> : null}
          </div>
          <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between" }}>
            <div style={{ display: "flex", flexDirection: "column" }}>
              <div style={{ fontSize: 22, color: "#55555f", fontWeight: 600 }}>Delivered to Bangladesh</div>
              <div style={{ fontSize: 64, fontWeight: 800, color: "#5b3df5" }}>{bdt(price)}</div>
            </div>
            <div style={{ display: "flex", fontSize: 20, color: "#55555f", fontWeight: 600 }}>Price verified at checkout</div>
          </div>
        </div>
      </div>
    ),
    size,
  );
}
