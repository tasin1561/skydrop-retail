<?php

namespace App\Filament\Resources\PreorderRules;

use App\Filament\Resources\FlashSales\Schemas\FlashSaleForm;
use App\Filament\Resources\PreorderRules\Pages\CreatePreorderRule;
use App\Filament\Resources\PreorderRules\Pages\EditPreorderRule;
use App\Filament\Resources\PreorderRules\Pages\ListPreorderRules;
use App\Filament\Support\Admin;
use App\Models\Category;
use App\Models\PreorderRule;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Which out-of-stock products may be pre-ordered, with the deposit % and ETA. */
class PreorderRuleResource extends Resource
{
    protected static ?string $model = PreorderRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Pre-order rules';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                ToggleButtons::make('scope')->options(['product' => 'One product', 'category' => 'A category'])->default('product')->inline()->live()->required(),
                Select::make('market')->label('Store')->options(Admin::MARKETS)->placeholder('All stores')->visible(fn (Get $get) => $get('scope') === 'category'),
                Select::make('target')->label('Product')->required()->searchable()->columnSpanFull()->visible(fn (Get $get) => $get('scope') === 'product')
                    ->getSearchResultsUsing(fn (string $search) => FlashSaleForm::search($search))
                    ->getOptionLabelUsing(fn ($value) => FlashSaleForm::label(Product::query()->find($value))),
                Select::make('target')->label('Category')->required()->visible(fn (Get $get) => $get('scope') === 'category')
                    ->options(fn () => Category::query()->where('market', '!=', Category::UNIFIED)->distinct()->orderBy('slug')->pluck('name', 'slug')->all() + ['electronics' => 'Electronics', 'fashion' => 'Fashion', 'beauty' => 'Beauty', 'grocery' => 'Grocery', 'home' => 'Home']),
                TextInput::make('deposit_pct')->label('Deposit')->numeric()->minValue(5)->maxValue(100)->default(30)->suffix('%')->required(),
                TextInput::make('eta_days')->label('ETA after order')->numeric()->minValue(1)->maxValue(180)->default(21)->suffix('days')->required(),
                TextInput::make('note')->maxLength(255)->columnSpanFull()->placeholder('Shown to shoppers, e.g. "New stock lands in India mid-December"'),
                Toggle::make('active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('scope')->badge(),
            TextColumn::make('target')->searchable()->description(fn (PreorderRule $r) => $r->scope === 'product'
                ? mb_strimwidth((string) Product::query()->whereKey($r->target)->value('title'), 0, 60, '…') : ($r->market ? Admin::marketLabel($r->market) : 'All stores')),
            TextColumn::make('deposit_pct')->label('Deposit')->suffix('%'),
            TextColumn::make('eta_days')->label('ETA')->suffix(' days'),
            IconColumn::make('active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreorderRules::route('/'),
            'create' => CreatePreorderRule::route('/create'),
            'edit' => EditPreorderRule::route('/{record}/edit'),
        ];
    }
}
