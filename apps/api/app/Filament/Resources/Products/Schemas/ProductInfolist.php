<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\Admin;
use App\Models\Product;
use App\Services\PricingService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->size(TextSize::Large)->weight('bold')->columnSpanFull(),
                        TextEntry::make('market')->badge()->formatStateUsing(fn ($state) => Admin::marketLabel($state)),
                        TextEntry::make('id')->label('Source id')->copyable()
                            ->url(fn (Product $r) => Admin::sourceUrl($r->market, $r->id), true)
                            ->suffix(' ↗'),
                        TextEntry::make('brand')->placeholder('—'),
                        TextEntry::make('category'),
                        TextEntry::make('price_inr')->label('Price (INR)')->money('INR'),
                        TextEntry::make('mrp_inr')->label('MRP (INR)')->money('INR'),
                        TextEntry::make('price_bdt')->label('Shopper price (BDT)')->weight('bold')->color('primary')
                            ->state(fn (Product $r) => Admin::bdt(app(PricingService::class)->priceBdt($r->price_inr, $r->category))),
                        TextEntry::make('rating')->formatStateUsing(fn (Product $r) => $r->rating.' ★ ('.number_format($r->reviews).' reviews)'),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->columns(2)
                    ->schema([
                        IconEntry::make('hidden')->boolean()->trueColor('danger')->falseColor('success')
                            ->trueIcon('heroicon-o-eye-slash')->falseIcon('heroicon-o-eye'),
                        IconEntry::make('in_stock')->boolean(),
                        IconEntry::make('detailed')->boolean(),
                        IconEntry::make('pincode_verified')->label('Deliverable 700001')->boolean(),
                        IconEntry::make('demo')->boolean(),
                        TextEntry::make('source')->label('Last written by')->badge(),
                        TextEntry::make('price_checked_at')->since()->placeholder('never'),
                        TextEntry::make('details_fetched_at')->since()->placeholder('never'),
                        TextEntry::make('views')->numeric(),
                    ]),
                Tabs::make('Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Images')->schema([
                            ImageEntry::make('gallery')->hiddenLabel()
                                ->state(fn (Product $r) => self::images($r))
                                ->imageHeight(160)->extraImgAttributes(['loading' => 'lazy', 'style' => 'object-fit:contain;background:#fff;border-radius:.5rem'])
                                ->placeholder('No images yet'),
                        ]),
                        Tab::make('Variants')->schema([
                            RepeatableEntry::make('details.variants')->hiddenLabel()
                                ->state(fn (Product $r) => self::variants($r))
                                ->table([
                                    RepeatableEntry\TableColumn::make('Option'),
                                    RepeatableEntry\TableColumn::make('Value'),
                                    RepeatableEntry\TableColumn::make('Product'),
                                    RepeatableEntry\TableColumn::make('Price'),
                                ])
                                ->schema([
                                    TextEntry::make('option'),
                                    TextEntry::make('value'),
                                    TextEntry::make('link')->html(),
                                    TextEntry::make('price'),
                                ])
                                ->placeholder('No variants'),
                        ]),
                        Tab::make('Specs')->schema([
                            KeyValueEntry::make('specs')->hiddenLabel()->keyLabel('Spec')->valueLabel('Value')
                                ->state(fn (Product $r) => self::flat($r->details?->specs))
                                ->placeholder('No specs'),
                            TextEntry::make('details.bullets')->label('Highlights')->listWithLineBreaks()->bulleted()->placeholder('—'),
                            TextEntry::make('details.weight')->label('Weight')->placeholder('—'),
                            TextEntry::make('details.delivery_text')->label('Delivery to Kolkata')->placeholder('—'),
                        ]),
                        Tab::make('Raw JSON')->schema([
                            TextEntry::make('raw')->hiddenLabel()
                                ->state(fn (Product $r) => Admin::jsonHtml([
                                    'product' => $r->attributesToArray(),
                                    'details' => $r->details?->attributesToArray(),
                                ])),
                        ]),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    public static function images(Product $r): array
    {
        $imgs = $r->details?->images ?? [];
        $urls = array_map(fn ($i) => is_array($i) ? ($i['large'] ?? $i['url'] ?? $i['hiRes'] ?? reset($i)) : $i, $imgs);
        if ($r->image) {
            array_unshift($urls, $r->image);
        }

        return array_values(array_unique(array_filter($urls, 'is_string')));
    }

    /** Normalises variants from either {dimension: [{value, asin}]} or [{name, value, asin}] shapes. */
    public static function variants(Product $r): array
    {
        $out = [];
        foreach ((array) ($r->details?->variants ?? []) as $k => $v) {
            if (is_array($v) && array_is_list($v) && is_string($k)) {
                foreach ($v as $opt) {
                    $out[] = self::variantRow($k, $opt, $r->market);
                }
            } elseif (is_array($v)) {
                $out[] = self::variantRow((string) ($v['dimension'] ?? $v['name'] ?? $v['type'] ?? (is_string($k) ? $k : '')), $v, $r->market);
            }
        }

        return $out;
    }

    private static function variantRow(string $option, mixed $v, string $market): array
    {
        $v = is_array($v) ? $v : ['value' => $v];
        $value = $v['value'] ?? $v['label'] ?? $v['title'] ?? null;
        if (! is_scalar($value)) {
            $value = json_encode($value);
        }
        $price = $v['priceInr'] ?? $v['price_inr'] ?? $v['price'] ?? null;

        $id = $v['asin'] ?? $v['id'] ?? $v['productId'] ?? null;

        return [
            'option' => $option,
            'value' => (string) $value,
            'link' => is_string($id) ? '<a href="'.e(Admin::sourceUrl($market, $id)).'" target="_blank" rel="noopener" style="color:#0b6bcb">'.e($id).' ↗</a>' : null,
            'price' => is_numeric($price) ? '₹'.number_format((float) $price) : null,
        ];
    }

    /** @return array<string, string> */
    public static function flat(mixed $specs): array
    {
        $out = [];
        foreach ((array) $specs as $k => $v) {
            if (is_array($v) && isset($v['name'])) {
                $out[(string) $v['name']] = is_scalar($v['value'] ?? null) ? (string) $v['value'] : json_encode($v['value'] ?? null);
            } elseif (is_array($v)) {
                foreach (self::flat($v) as $kk => $vv) {
                    $out[is_int($k) ? $kk : "{$k} · {$kk}"] = $vv;
                }
            } else {
                $out[(string) $k] = (string) $v;
            }
        }

        return $out;
    }
}
