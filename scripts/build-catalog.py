"""Build the demo catalog for Skydrop Retail.

Amazon products come from real Oxylabs test responses (amazon.in, pincode 700001).
Flipkart and BigBasket products are original demo items until their providers are wired up.

Usage: python3 scripts/build-catalog.py <oxylabs_raw_dir> apps/web/src/data/catalog.json
"""
import html, json, re, sys
from pathlib import Path

raw = Path(sys.argv[1])
out = Path(sys.argv[2])

def load(name):
    p = raw / f"{name}.json"
    if not p.exists():
        return None
    try:
        return json.loads(p.read_text())["results"][0]["content"]
    except Exception:
        return None

def img_large(url):
    if not url:
        return None
    # ask the CDN for a 600px version instead of the tiny search thumbnail
    return re.sub(r"\._[^/]*_\.", "._AC_SL600_.", url)

products = {}

SEARCHES = {
    "s_juice_p1": "grocery", "s_juice_p2": "grocery",
    "s_earbuds": "electronics", "s_shoes": "fashion", "s_lipstick": "beauty",
}
for name, cat in SEARCHES.items():
    c = load(name)
    if not c:
        continue
    for r in c["results"].get("organic", []):
        if r.get("is_sponsored") or not r.get("price"):
            continue
        a = r["asin"]
        if a in products:
            continue
        products[a] = {
            "id": a, "market": "amazon", "category": cat,
            "title": r["title"], "brand": r.get("brand") or r.get("manufacturer") or "",
            "priceInr": r["price"], "mrpInr": r.get("price_strikethrough") or r["price"],
            "rating": r.get("rating") or 0, "reviews": r.get("reviews_count") or 0,
            "image": img_large(r.get("url_image")), "images": [img_large(r.get("url_image"))],
            "prime": bool(r.get("is_prime")), "bestSeller": bool(r.get("best_seller")),
            "amazonsChoice": bool(r.get("is_amazons_choice")),
            "salesVolume": r.get("sales_volume") or "", "detailed": False,
        }

for name, cat in {"bs_electronics": "electronics", "bs_fruitjuice_retry": "grocery"}.items():
    c = load(name)
    if not c:
        continue
    for r in c.get("results", []):
        a = r.get("asin")
        if not a or a in products or not r.get("price"):
            continue
        products[a] = {
            "id": a, "market": "amazon", "category": cat, "title": r["title"], "brand": "",
            "priceInr": r["price"], "mrpInr": r["price"], "rating": r.get("rating") or 0,
            "reviews": r.get("ratings_count") or 0, "image": img_large(r.get("image_url")),
            "images": [img_large(r.get("image_url"))], "prime": False, "bestSeller": True,
            "amazonsChoice": False, "salesVolume": "", "detailed": False,
        }

# full product details from the product calls
for p in sorted(raw.glob("p_*.json")):
    try:
        c = json.loads(p.read_text())["results"][0]["content"]
    except Exception:
        continue
    a = c.get("asin")
    base = products.get(a, {"id": a, "market": "amazon", "category": "electronics"})
    pd = c.get("product_details") or {}
    weight = next((v for k, v in pd.items() if "weight" in k.lower()), None)
    variants = []
    for v in c.get("variation") or []:
        dims = v.get("dimensions") or {}
        variants.append({
            "id": v.get("asin"), "label": " / ".join(str(x) for x in dims.values()),
            "dimensions": dims, "swatch": v.get("tooltip_image"), "selected": bool(v.get("selected")),
        })
    reviews = [{
        "author": r.get("author", ""), "rating": r.get("rating", 0), "title": r.get("title", ""),
        "content": (r.get("content") or "")[:600], "date": r.get("timestamp", ""),
    } for r in (c.get("reviews") or [])[:6]]
    specs = {k.replace("_", " ").capitalize(): str(v) for k, v in pd.items()
             if isinstance(v, (str, int, float)) and k not in ("asin",)}
    bullets = c.get("bullet_points") or ""
    bullets = [b.strip() for b in re.split(r"\n|(?<=\.)\s(?=\[)", bullets) if b.strip()][:8]
    delivery = c.get("delivery") or []
    base.update({
        "title": c.get("title") or base.get("title"), "brand": c.get("brand") or base.get("brand", ""),
        "priceInr": c.get("price") or base.get("priceInr"),
        "mrpInr": c.get("price_strikethrough") or c.get("price") or base.get("mrpInr"),
        "rating": c.get("rating") or base.get("rating", 0),
        "reviews": c.get("reviews_count") or base.get("reviews", 0),
        "images": c.get("images") or base.get("images", []),
        "image": (c.get("images") or [base.get("image")])[0],
        "prime": bool(c.get("is_prime_eligible")), "stock": c.get("stock", ""),
        "bullets": bullets, "specs": dict(list(specs.items())[:16]),
        "weight": str(weight) if weight else None, "variants": variants,
        "aplus": (c.get("description_images") or [])[:6], "hasVideos": bool(c.get("has_videos")),
        "reviewList": reviews,
        "starBreakdown": c.get("rating_stars_distribution") or [],
        "deliveryKolkata": delivery[0]["date"]["by"] if delivery and delivery[0].get("date") else None,
        "categoryPath": [x["name"] for x in ((c.get("category") or [{}])[0].get("ladder") or [])],
        "detailed": True,
    })
    if (c.get("price") or 0) == 0:
        base["unavailable"] = True
    products[a] = base

# videos from the raw-HTML test page
h = raw / "html_B0FMDLD86P.json"
if h.exists() and "B0FMDLD86P" in products:
    d = html.unescape(json.loads(h.read_text())["results"][0]["content"])
    vids = sorted(set(re.findall(r'"videoURL":"(https://[^"]+?\.m3u8)"', d)))
    thumbs = re.findall(r'"videoImageUrlUnchanged":"(https://[^"]+?)"', d)
    products["B0FMDLD86P"]["videos"] = [{"hls": v, "thumb": thumbs[i] if i < len(thumbs) else None}
                                         for i, v in enumerate(vids)]
    # offer cards ("itembox-*") from the same raw page
    types = {"InstantBankDiscount": "bank", "GCCashback": "cashback", "NoCostEmi": "emi", "Partner": "partner"}
    offers = []
    for m in re.finditer(r'id="itembox-([A-Za-z]+)"', d):
        seg = re.sub(r"<script.*?</script>|<style.*?</style>", " ", d[m.start():m.start() + 2500], flags=re.S)
        txt = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", seg.split(">", 1)[1])).strip()
        mt = re.match(r"(Bank Offer|Cashback|No Cost EMI|Partner Offers)\s+(.+?)\s+(\d+) offers?", txt)
        if not mt:
            continue
        saving = re.search(r"₹\s?([\d,.]+)", mt.group(2))
        offers.append({"type": types.get(m.group(1), "other"), "title": mt.group(1), "detail": mt.group(2),
                       "savingInr": float(saving.group(1).replace(",", "")) if saving else None,
                       "appliesToOurPrice": False, "count": int(mt.group(3))})
    products["B0FMDLD86P"]["offers"] = offers

amazon = [p for p in products.values() if p.get("priceInr") and not p.get("unavailable")]

# ---- original demo items for the other two marketplaces (no real data yet) ----
def demo(market, cat, idx, title, brand, inr, mrp, rating, reviews, hue, icon, weight, variants=None):
    return {
        "id": f"{market[:2].upper()}{cat[:3].upper()}{idx:03d}", "market": market, "category": cat,
        "title": title, "brand": brand, "priceInr": inr, "mrpInr": mrp, "rating": rating,
        "reviews": reviews, "image": None, "images": [], "placeholder": {"hue": hue, "icon": icon},
        "prime": False, "bestSeller": idx < 3, "amazonsChoice": False, "salesVolume": "",
        "detailed": True, "weight": weight, "variants": variants or [], "demo": True,
        "bullets": ["Demo product: real listings appear once this store is connected."],
        "specs": {"Brand": brand, "Weight": weight}, "reviewList": [], "starBreakdown": [],
        "offers": demo_offers(market, idx, inr),
    }

def demo_offers(market, idx, inr):
    # Demo offers for demo listings only, so the offer UI can be seen before live data arrives.
    if market == "flipkart":
        offers = [{"type": "bank", "title": "Bank Offer", "detail": "10% off on select bank credit cards, up to ₹750", "appliesToOurPrice": False}]
        if idx % 3 == 0:
            offers.insert(0, {"type": "special_price", "title": "Special Price", "detail": f"Extra ₹{max(20, round(inr * 0.05))} off (price inclusive of discount)", "savingInr": max(20, round(inr * 0.05)), "appliesToOurPrice": True})
        return offers
    if market == "bigbasket" and idx % 2 == 0:
        return [{"type": "combo", "title": "Combo offer", "detail": "Buy 2, save 10% on the second pack", "appliesToOurPrice": False}]
    return []

def sizes(*labels):
    return [{"id": None, "label": l, "dimensions": {"Size": l}, "swatch": None, "selected": i == 0}
            for i, l in enumerate(labels)]

flip = [
    ("fashion", "Men's Slim Fit Cotton Shirt", "Northline", 699, 1499, 4.2, 18200, 210, "shirt", "250 g", sizes("S", "M", "L", "XL")),
    ("fashion", "Women's Printed Kurta Set", "Saanjh", 1199, 2499, 4.3, 9400, 330, "shirt", "450 g", sizes("S", "M", "L")),
    ("fashion", "Unisex Everyday Sneakers", "Stride Co", 1299, 2999, 4.1, 26100, 20, "shoe", "700 g", sizes("7", "8", "9", "10")),
    ("fashion", "Men's Stretch Denim Jeans", "Blue Mill", 899, 1999, 4.0, 15400, 220, "shirt", "550 g", sizes("30", "32", "34", "36")),
    ("electronics", "5G Smartphone 8 GB / 128 GB", "Volt", 14999, 18999, 4.4, 51200, 200, "phone", "205 g"),
    ("electronics", "Smartwatch with AMOLED Display", "Pulse", 1999, 4999, 4.1, 33800, 260, "watch", "60 g"),
    ("electronics", "Wireless Neckband, 30 h Battery", "Tonic", 899, 2499, 4.0, 41700, 280, "audio", "45 g"),
    ("home", "Non-stick Cookware Set, 3 Pieces", "Kitchora", 1499, 2999, 4.2, 7200, 30, "home", "2.4 kg"),
    ("home", "Cotton Double Bedsheet with 2 Pillow Covers", "Dreamloom", 649, 1599, 4.3, 22300, 190, "home", "800 g"),
    ("beauty", "Vitamin C Face Serum 30 ml", "Glowra", 399, 699, 4.1, 12900, 320, "beauty", "90 g"),
    ("beauty", "Matte Liquid Lipstick Set of 4", "Velvetta", 499, 999, 4.0, 8600, 345, "beauty", "60 g"),
    ("fashion", "Women's Tote Handbag", "Carryall", 799, 1999, 4.2, 6100, 15, "bag", "500 g"),
]
bb = [
    ("grocery", "Basmati Rice, Extra Long Grain 5 kg", "Harvest Gold", 649, 799, 4.4, 15800, 40, "grain", "5 kg"),
    ("grocery", "Cold Pressed Mustard Oil 1 L", "Pure Press", 189, 230, 4.3, 9700, 50, "oil", "1 kg"),
    ("grocery", "Assam Tea, Strong Blend 500 g", "Tea Garden", 249, 299, 4.5, 21300, 25, "tea", "550 g"),
    ("grocery", "Premium Cashews W320, 500 g", "Nutfield", 529, 699, 4.4, 6400, 35, "nut", "520 g"),
    ("grocery", "Garam Masala Powder 200 g", "Spice Route", 99, 120, 4.3, 11200, 15, "spice", "220 g"),
    ("grocery", "Instant Noodles, Pack of 12", "Slurp", 168, 180, 4.2, 30400, 45, "noodle", "840 g"),
    ("grocery", "Dark Chocolate 70%, 4 Bars", "Cocoa Lane", 399, 480, 4.5, 3900, 20, "choco", "400 g"),
    ("grocery", "Organic Jaggery Powder 1 kg", "Farm Roots", 145, 175, 4.2, 5300, 30, "grain", "1 kg"),
    ("grocery", "Mixed Fruit Juice 1 L, Pack of 2", "Fresh Squeeze", 210, 250, 4.1, 8800, 10, "juice", "2.1 kg"),
    ("home", "Dishwash Liquid 750 ml", "Sparkle", 115, 135, 4.3, 19100, 190, "home", "800 g"),
    ("beauty", "Herbal Neem Face Wash 150 ml", "Leafline", 145, 175, 4.2, 14600, 120, "beauty", "170 g"),
    ("grocery", "Roasted Makhana, Peri Peri 90 g", "Crunchbox", 129, 149, 4.0, 4200, 5, "nut", "100 g"),
]
flipkart = [demo("flipkart", r[0], i, r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9],
                 r[10] if len(r) > 10 else None) for i, r in enumerate(flip)]
bigbasket = [demo("bigbasket", r[0], i, r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9]) for i, r in enumerate(bb)]

catalog = {
    "generatedFrom": "Oxylabs test, amazon.in, pincode 700001, 7 Nov 2026",
    "products": amazon + flipkart + bigbasket,
}
out.parent.mkdir(parents=True, exist_ok=True)
out.write_text(json.dumps(catalog, ensure_ascii=False))
print(f"amazon={len(amazon)} detailed={sum(1 for p in amazon if p.get('detailed'))} "
      f"flipkart={len(flipkart)} bigbasket={len(bigbasket)} -> {out}")
