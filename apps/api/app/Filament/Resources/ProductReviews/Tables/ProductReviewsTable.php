<?php

namespace App\Filament\Resources\ProductReviews\Tables;

use App\Filament\Support\Admin;
use App\Models\Product;
use App\Models\ProductReview;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductReviewsTable
{
    public const STATUS_COLORS = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];

    public static function configure(Table $table, bool $nested = false): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))->color('warning')->sortable(),
                TextColumn::make('title')->weight('bold')->searchable()->limit(50)
                    ->description(fn (ProductReview $r) => str($r->body)->limit(120)),
                TextColumn::make('product_id')->label('Product')->searchable()
                    ->description(fn (ProductReview $r) => str(Product::query()->whereKey($r->product_id)->value('title'))->limit(40)),
                TextColumn::make('user.name')->label('Customer'),
                IconColumn::make('verified_purchase')->label('Verified')->boolean(),
                TextColumn::make('status')->badge()->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default($nested ? null : 'pending'),
                SelectFilter::make('rating')->options([1 => '1 ★', 2 => '2 ★', 3 => '3 ★', 4 => '4 ★', 5 => '5 ★']),
                TernaryFilter::make('verified_purchase'),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approve(),
                self::reject(),
                DeleteAction::make()->visible(fn () => Admin::canManage()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')->label('Approve')->icon(Heroicon::OutlinedCheck)->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'approved'])),
                    BulkAction::make('rejectAll')->label('Reject')->icon(Heroicon::OutlinedXMark)->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'rejected'])),
                ]),
            ]);
    }

    public static function approve(): Action
    {
        return Action::make('approve')->icon(Heroicon::OutlinedCheck)->color('success')
            ->visible(fn (ProductReview $record) => $record->status !== 'approved')
            ->action(fn (ProductReview $record) => $record->update(['status' => 'approved']));
    }

    public static function reject(): Action
    {
        return Action::make('reject')->icon(Heroicon::OutlinedXMark)->color('danger')
            ->visible(fn (ProductReview $record) => $record->status !== 'rejected')
            ->action(fn (ProductReview $record) => $record->update(['status' => 'rejected']));
    }
}
