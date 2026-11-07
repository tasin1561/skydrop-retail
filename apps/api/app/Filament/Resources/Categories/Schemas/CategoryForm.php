<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Resources\Categories\CategoryAdmin;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Tree nodes (market = all): names (EN/BN), icon, image, parent, pricing group, store sources and keywords.
 * Legacy per-store rows keep their browse node / seed pages fields. Path, depth and order are derived on save.
 */
class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        $tree = fn (Get $get) => $get('market') === Category::UNIFIED;

        return $schema
            ->components([
                Section::make()->columns(2)->columnSpanFull()->schema([
                    Select::make('market')->options(CategoryAdmin::markets())->default(Category::UNIFIED)->required()->live(),
                    TextInput::make('name')->required()->maxLength(100)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('name_bn')->label('Name (Bangla)')->maxLength(100)->visible($tree),
                    TextInput::make('slug')->label('URL segment')->required()->maxLength(60)->alphaDash()
                        ->helperText(fn (Get $get) => $get('market') === Category::UNIFIED ? 'Last part of the path; the full path is rebuilt from the parent on save.' : null),
                    Select::make('parent_id')->label('Parent category')->searchable()
                        ->options(fn (Get $get, ?Category $record) => $get('market') === Category::UNIFIED
                            ? CategoryAdmin::nodeOptions(true, $record?->id)
                            : Category::query()->where('market', $get('market'))->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('Top level'),
                    TextInput::make('path')->disabled()->dehydrated(false)->visible($tree)->placeholder('set on save'),
                    Select::make('icon')->options(array_combine(CategoryAdmin::ICONS, CategoryAdmin::ICONS))->searchable()->visible($tree),
                    TextInput::make('image')->label('Image URL')->url()->maxLength(1000)->visible($tree),
                    Select::make('pricing_group')->options(array_combine(Category::PRICING_GROUPS, array_map('ucfirst', Category::PRICING_GROUPS)))
                        ->placeholder('Inherit from parent')->visible($tree)
                        ->helperText('Products in this node use this pricing rule (products.category).'),
                    TextInput::make('sort')->numeric()->minValue(0)->default(0)->visible($tree),
                    Toggle::make('featured')->visible($tree),
                    Toggle::make('active')->default(true),
                    TextInput::make('browse_node')->label('Marketplace category id')->maxLength(60)->hidden($tree),
                    TextInput::make('seed_pages')->label('Pages to seed')->numeric()->minValue(0)->maxValue(50)->default(2)
                        ->helperText('Search result pages fetched when warming the catalog (each page is a paid call).'),
                ]),
                Section::make('Matching keywords')->columnSpanFull()->visible($tree)
                    ->description('Matched against store breadcrumbs and product titles (singular/plural handled). The name is always included.')
                    ->schema([TagsInput::make('keywords')->hiddenLabel()->splitKeys(['Tab', ','])]),
                Section::make('Where it is sold')->columnSpanFull()->visible($tree)->columns(3)
                    ->description('Leave a store\'s query empty when that store does not sell this category.')
                    ->schema([
                        Fieldset::make('Amazon (amazon.in)')->columns(1)->columnSpan(1)->schema([
                            TextInput::make('sources.amazon.query')->label('Search query'),
                            Select::make('sources.amazon.search_alias')->label('Search alias')->options(array_combine(CategoryAdmin::AMAZON_ALIASES, CategoryAdmin::AMAZON_ALIASES)),
                            TextInput::make('sources.amazon.node')->label('Browse node id')->helperText('Filled by retail:import-amazon-nodes.'),
                        ]),
                        Fieldset::make('Flipkart (flipkart.com)')->columns(1)->columnSpan(1)->schema([
                            TextInput::make('sources.flipkart.query')->label('Search query'),
                            TextInput::make('sources.flipkart.path')->label('Category path (optional)'),
                        ]),
                        Fieldset::make('BigBasket (bigbasket.com)')->columns(1)->columnSpan(1)->schema([
                            TextInput::make('sources.bigbasket.query')->label('Search query'),
                            TextInput::make('sources.bigbasket.slug')->label('Category slug (optional)'),
                        ]),
                    ]),
            ]);
    }
}
