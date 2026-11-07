<?php

namespace App\Services\Tracking;

use App\Services\PricingService;
use Generator;
use Illuminate\Support\Facades\DB;

/** Rows for the Meta catalog CSV and the Google Merchant XML feed, built from the products table. */
class ProductFeed
{
    /** Our categories → Google product taxonomy. */
    public const GOOGLE_CATEGORY = [
        'electronics' => 'Electronics',
        'fashion' => 'Apparel & Accessories',
        'beauty' => 'Health & Beauty > Personal Care > Cosmetics',
        'grocery' => 'Food, Beverages & Tobacco',
        'home' => 'Home & Garden',
    ];

    public const STORE_BRAND = ['amazon' => 'Mega Store', 'flipkart' => 'Style Bazaar', 'bigbasket' => 'Daily Basket'];

    public function __construct(private PricingService $pricing) {}

    /**
     * Visible products with a price. Prices come from PricingService::productBdt (the single price source).
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(?string $store = null): Generator
    {
        $web = rtrim((string) config('retail.web_url'), '/');
        $q = DB::table('products')
            ->leftJoin('product_details', 'product_details.product_id', '=', 'products.id')
            ->where('products.hidden', false)
            ->where('products.price_inr', '>', 0)
            ->when($store, fn ($q, $s) => $q->where('products.market', $s))
            ->orderBy('products.id')
            ->select('products.*', 'product_details.category_path', 'product_details.bullets');

        foreach ($q->cursor() as $p) {
            $sale = $this->pricing->productBdt($p);
            if ($sale <= 0) {
                continue;
            }
            $mrp = (float) $p->mrp_inr > 0 ? $this->pricing->priceBdt((float) $p->mrp_inr, (string) $p->category) : 0;
            $path = json_decode((string) $p->category_path, true);
            $bullets = json_decode((string) $p->bullets, true);
            $description = is_array($bullets) && $bullets ? implode(' ', array_slice(array_map('strval', $bullets), 0, 5)) : $p->title;

            yield [
                'id' => (string) $p->id,
                'title' => mb_substr(trim((string) $p->title), 0, 150),
                'description' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 0, 5000),
                'availability' => $p->in_stock ? 'in stock' : 'out of stock',
                'condition' => 'new',
                'price' => ($mrp > $sale ? $mrp : $sale).' BDT',
                'sale_price' => $mrp > $sale ? $sale.' BDT' : '',
                'link' => $web.'/p/'.rawurlencode((string) $p->id),
                'image_link' => (string) ($p->image ?? ''),
                'brand' => trim((string) $p->brand) ?: (self::STORE_BRAND[$p->market] ?? 'Skydrop Retail'),
                'google_product_category' => self::GOOGLE_CATEGORY[$p->category] ?? '',
                'product_type' => is_array($path) && $path ? implode(' > ', array_map('strval', $path)) : ucfirst((string) $p->category),
                'custom_label_0' => (string) $p->market,
            ];
        }
    }

    public function csv(?string $store = null): string
    {
        $out = fopen('php://temp', 'r+');
        $headerWritten = false;
        foreach ($this->rows($store) as $row) {
            if (! $headerWritten) {
                fputcsv($out, array_keys($row), ',', '"', '');
                $headerWritten = true;
            }
            fputcsv($out, array_values($row), ',', '"', '');
        }
        if (! $headerWritten) {
            fputcsv($out, ['id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'brand',
                'google_product_category', 'product_type', 'custom_label_0'], ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }

    public function googleXml(?string $store = null): string
    {
        $w = new \XMLWriter;
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('rss');
        $w->writeAttribute('version', '2.0');
        $w->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $w->startElement('channel');
        $w->writeElement('title', 'Skydrop Retail');
        $w->writeElement('link', (string) config('retail.web_url'));
        $w->writeElement('description', 'Indian store products delivered to Bangladesh by Skydrop');
        foreach ($this->rows($store) as $row) {
            $w->startElement('item');
            foreach ($row as $k => $v) {
                if ($v === '') {
                    continue;
                }
                $w->writeElement('g:'.$k, $v);
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }
}
