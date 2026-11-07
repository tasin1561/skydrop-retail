<?php

namespace App\Filament\Resources\FlashSales\Schemas;

use App\Filament\Resources\Banners\Schemas\BannerForm;
use App\Filament\Support\Admin;
use App\Models\Product;
use App\Services\Cms\FlashSaleService;
use App\Services\PricingService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FlashSaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([ // two columns: date-time pickers were too narrow to show the time
                TextInput::make('title')->required()->maxLength(160)->placeholder('Midnight electronics flash'),
                Select::make('store')->options(BannerForm::stores())->default('all')->required(),
                DateTimePicker::make('starts_at')->required()->seconds(false)->default(now()->startOfHour()->addHour())->helperText('Bangladesh time'),
                DateTimePicker::make('ends_at')->required()->seconds(false)->after('starts_at')->helperText('Bangladesh time'),
                TextInput::make('max_qty')->label('Max qty per order (per product)')->numeric()->minValue(1)->placeholder('No limit'),
                Toggle::make('active')->default(true),
            ]),
            Section::make('Products')
                ->description('Extra Skydrop discount funded from our margin. It is capped at the margin, so we never sell below cost.')
                ->schema([
                    Repeater::make('items')->relationship()->orderColumn('sort')->defaultItems(0)->columns(4)->addActionLabel('Add product')
                        ->itemLabel(fn (array $state) => $state['product_id'] ? mb_strimwidth((string) Product::query()->whereKey($state['product_id'])->value('title'), 0, 80, '…') : null)
                        ->schema([
                            Select::make('product_id')->label('Product')->required()->searchable()->columnSpan(2)
                                ->getSearchResultsUsing(fn (string $search) => self::search($search))
                                ->getOptionLabelUsing(fn ($value) => self::label(Product::query()->find($value)))
                                ->live()->distinct(),
                            TextInput::make('off_pct')->label('Extra % off')->numeric()->minValue(0)->maxValue(50)->suffix('%')->live(onBlur: true)
                                ->requiredWithout('off_bdt'),
                            TextInput::make('off_bdt')->label('or fixed ৳ off')->numeric()->minValue(0)->prefix('৳')->live(onBlur: true),
                            Text::make(fn (Get $get) => self::pricePreview($get('product_id'), $get('off_pct'), $get('off_bdt')))->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    /** @return array<string, string> */
    public static function search(string $search): array
    {
        $s = trim($search);

        return Product::query()->where('hidden', false)
            ->where(fn ($q) => $q->where('id', $s)->orWhere('id', 'like', $s.'%')->orWhere('title', 'like', '%'.$s.'%'))
            ->orderByRaw('case when id = ? then 0 else 1 end', [$s])->limit(30)->get()
            ->mapWithKeys(fn (Product $p) => [$p->id => self::label($p)])->all();
    }

    public static function label(?Product $p): string
    {
        return $p ? '['.$p->market.'] '.mb_strimwidth($p->title, 0, 70, '…').' · '.$p->id.' · '.Admin::bdt(app(PricingService::class)->basePriceBdt($p)) : '';
    }

    public static function pricePreview(?string $id, mixed $pct, mixed $bdt): string
    {
        $p = $id ? Product::query()->find($id) : null;
        if (! $p) {
            return 'Pick a product to see the flash price.';
        }
        $regular = app(PricingService::class)->basePriceBdt($p);
        $off = app(FlashSaleService::class)->amount(['offPct' => (float) $pct ?: null, 'offBdt' => (int) $bdt ?: null], $regular, (string) $p->category);

        return 'Regular '.Admin::bdt($regular).' → flash '.Admin::bdt($regular - $off).' (−'.Admin::bdt($off).')';
    }
}
