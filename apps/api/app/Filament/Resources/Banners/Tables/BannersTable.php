<?php

namespace App\Filament\Resources\Banners\Tables;

use App\Filament\Resources\Banners\Schemas\BannerForm;
use App\Filament\Support\Admin;
use App\Models\Banner;
use App\Support\BizTime;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('preview')->state(fn (Banner $r) => $r->imageUrl())->height(48)->width(120),
                TextColumn::make('title')->searchable()->weight('bold')->description(fn (Banner $r) => $r->subtitle),
                TextColumn::make('placement')->badge()->formatStateUsing(fn ($state) => Banner::PLACEMENTS[$state] ?? $state),
                TextColumn::make('store')->badge()->formatStateUsing(fn ($state) => BannerForm::stores()[$state] ?? $state),
                TextColumn::make('schedule')->state(fn (Banner $r) => ($r->starts_at?->copy()->setTimezone(BizTime::TZ)->format('d M H:i') ?? 'now').' → '.($r->ends_at?->copy()->setTimezone(BizTime::TZ)->format('d M H:i') ?? 'no end'))
                    ->color(fn (Banner $r) => $r->ends_at?->isPast() ? 'danger' : ($r->starts_at?->isFuture() ? 'warning' : 'success')),
                ToggleColumn::make('active'),
            ])
            ->filters([
                SelectFilter::make('placement')->options(Banner::PLACEMENTS),
                SelectFilter::make('store')->options(BannerForm::stores()),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())])]);
    }
}
