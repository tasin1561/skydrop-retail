<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Admin;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    Select::make('market')->options(Admin::MARKETS)->required()->live(),
                    TextInput::make('name')->required()->maxLength(100)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(60)->alphaDash(),
                    TextInput::make('browse_node')->label('Marketplace category id')->maxLength(60),
                    Select::make('parent_id')->label('Parent category')
                        ->options(fn (Get $get) => Category::query()->when($get('market'), fn ($q, $m) => $q->where('market', $m))->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    TextInput::make('seed_pages')->label('Pages to seed')->numeric()->minValue(0)->maxValue(50)->default(2)
                        ->helperText('Search result pages fetched when warming the catalog (each page is a paid call).'),
                    Toggle::make('active')->default(true),
                ]),
            ]);
    }
}
