<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use App\Filament\Support\Admin;
use App\Services\PricingService;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Rule')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('category')->required()->maxLength(40)->unique(ignoreRecord: true)
                            ->helperText('Product category slug (electronics, fashion, beauty, grocery, home), or * for the default rule.')
                            ->datalist(['*', 'electronics', 'fashion', 'beauty', 'grocery', 'home']),
                        TextInput::make('margin_pct')->label('Margin')->suffix('%')->numeric()->minValue(0)->maxValue(100)->required()->default(10)->live(debounce: 400),
                        TextInput::make('import_pct')->label('Import / customs')->suffix('%')->numeric()->minValue(0)->maxValue(100)->required()->default(10)->live(debounce: 400),
                        TextInput::make('default_weight_kg')->label('Default weight')->suffix('kg')->numeric()->minValue(0.01)->required()->default(0.5)
                            ->helperText('Used for shipping when a product has no weight.'),
                    ]),
                Section::make('Live price preview')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('sample_inr')->label('Sample store price')->prefix('₹')->numeric()->default(1000)
                            ->dehydrated(false)->live(debounce: 400),
                        Text::make(fn (Get $get) => self::preview($get)),
                    ]),
            ]);
    }

    /** Same formula as PricingService::priceBdt, but with the rule being edited instead of the saved one. */
    public static function preview(Get $get): HtmlString
    {
        $inr = (float) ($get('sample_inr') ?: 0);
        $margin = (float) $get('margin_pct') / 100;
        $import = (float) $get('import_pct') / 100;
        $fx = app(PricingService::class)->fx();
        $buffer = (float) Admin::setting('pricing.fx_buffer', config('retail.pricing.fx_buffer'));

        $base = $inr * $fx * (1 + $buffer);
        $total = (int) (ceil($base * (1 + $import) * (1 + $margin) / 10) * 10);
        $importBdt = $base * $import;
        $service = max(0, $total - round($base) - round($importBdt));
        $profit = $total - $base * (1 + $import);

        $row = fn ($l, $v) => "<div style='display:flex;justify-content:space-between;gap:1rem'><span style='opacity:.7'>{$l}</span><span>{$v}</span></div>";

        return new HtmlString(
            "<div style='display:grid;gap:.35rem;font-size:.875rem'>"
            .$row('FX rate (INR→BDT)', number_format($fx, 4).' + '.round($buffer * 100, 2).'% buffer')
            .$row('Store price in BDT', Admin::bdt(round($base)))
            .$row('Import / customs', Admin::bdt(round($importBdt)))
            .$row('Service (margin)', Admin::bdt($service))
            ."<hr style='opacity:.2'>"
            ."<div style='display:flex;justify-content:space-between;font-size:1.25rem;font-weight:700'><span>Shopper pays</span><span style='color:#5b3df5'>".Admin::bdt($total).'</span></div>'
            .$row('Our gross margin', Admin::bdt(round($profit)).($total > 0 ? ' ('.round($profit / $total * 100, 1).'%)' : ''))
            ."<div style='opacity:.6;font-size:.75rem'>Shipping is added at checkout per kg.</div>"
            .'</div>'
        );
    }
}
