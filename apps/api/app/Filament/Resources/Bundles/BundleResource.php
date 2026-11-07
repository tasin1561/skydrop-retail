<?php

namespace App\Filament\Resources\Bundles;

use App\Filament\Resources\Bundles\Pages\CreateBundle;
use App\Filament\Resources\Bundles\Pages\EditBundle;
use App\Filament\Resources\Bundles\Pages\ListBundles;
use App\Filament\Resources\FlashSales\Schemas\FlashSaleForm;
use App\Filament\Support\Admin;
use App\Models\Bundle;
use App\Models\Product;
use App\Services\Shopping\BundleService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Curated bundles: a fixed set of products with an extra discount when all are in the cart (capped at our margin). */
class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('title')->required()->maxLength(160)->columnSpan(2)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, ?Bundle $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->required()->maxLength(80)->alphaDash()->unique(ignoreRecord: true),
                Textarea::make('description')->rows(2)->columnSpanFull(),
                TextInput::make('image')->url()->maxLength(1000)->columnSpan(2)->placeholder('Optional cover image URL'),
                TextInput::make('discount_pct')->label('Extra discount')->numeric()->minValue(0)->maxValue(30)->suffix('%')->default(5)->required()
                    ->helperText('Funded from margin; capped at the margin of the items.'),
                DateTimePicker::make('starts_at')->seconds(false),
                DateTimePicker::make('ends_at')->seconds(false)->after('starts_at'),
                TextInput::make('sort')->numeric()->default(0),
                Toggle::make('active')->default(true),
            ]),
            Section::make('Products')->columnSpanFull()->description('The saving applies when every product is in the cart (per complete set).')->schema([
                Repeater::make('items')->relationship()->orderColumn('sort')->minItems(2)->defaultItems(2)->columns(4)->addActionLabel('Add product')
                    ->schema([
                        Select::make('product_id')->label('Product')->required()->searchable()->columnSpan(3)->distinct()
                            ->getSearchResultsUsing(fn (string $search) => FlashSaleForm::search($search))
                            ->getOptionLabelUsing(fn ($value) => FlashSaleForm::label(Product::query()->find($value))),
                        TextInput::make('qty')->numeric()->minValue(1)->maxValue(10)->default(1)->required(),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold')->description(fn (Bundle $b) => '/bundles/'.$b->slug),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('discount_pct')->label('Extra off')->suffix('%'),
                TextColumn::make('pricing')->label('Price')->state(function (Bundle $b) {
                    $a = app(BundleService::class)->toApi($b, false);

                    return Admin::bdt($a['totalBdt']).' (−'.Admin::bdt($a['discountBdt']).')';
                }),
                IconColumn::make('active')->boolean(),
                TextColumn::make('ends_at')->dateTime()->placeholder('No end'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBundles::route('/'),
            'create' => CreateBundle::route('/create'),
            'edit' => EditBundle::route('/{record}/edit'),
        ];
    }
}
