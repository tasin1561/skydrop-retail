<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Support\Admin;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('market')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('market')->badge()->formatStateUsing(fn ($state) => Admin::marketLabel($state))->sortable(),
                TextColumn::make('slug')->searchable()->color('gray'),
                TextColumn::make('browse_node')->label('Node')->toggleable(),
                TextColumn::make('products')->label('Products')->numeric()
                    ->state(fn (Category $r) => Product::query()->where('market', $r->market)->where('category', $r->slug)->count()),
                TextColumn::make('seed_pages')->label('Seed pages')->numeric(),
                ToggleColumn::make('active'),
            ])
            ->filters([
                SelectFilter::make('market')->options(Admin::MARKETS),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())]),
            ]);
    }
}
