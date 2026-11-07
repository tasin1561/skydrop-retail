<?php

namespace App\Filament\Resources\SupportRequests\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\SupportRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportRequestsTable
{
    public const STATUSES = ['open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved', 'rejected' => 'Rejected'];

    public const COLORS = ['open' => 'danger', 'in_review' => 'warning', 'resolved' => 'success', 'rejected' => 'gray'];

    public const TYPES = ['return' => 'Return', 'damaged' => 'Damaged', 'wrong_item' => 'Wrong item', 'other' => 'Other'];

    /** Allowed next statuses. */
    public const FLOW = ['open' => ['in_review', 'resolved', 'rejected'], 'in_review' => ['resolved', 'rejected', 'open'], 'resolved' => ['open'], 'rejected' => ['open']];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => self::TYPES[$state] ?? $state),
                TextColumn::make('order_id')->label('Order')->searchable()->weight('bold')
                    ->url(fn (SupportRequest $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextColumn::make('user.name')->label('Customer')->description(fn (SupportRequest $r) => $r->user?->phone),
                TextColumn::make('message')->limit(70)->wrap()->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->multiple(),
                SelectFilter::make('type')->options(self::TYPES),
            ])
            ->recordActions([
                ViewAction::make(),
                self::changeStatus(),
                EditAction::make(),
            ]);
    }

    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->schema(fn (SupportRequest $record) => [
                \Filament\Forms\Components\ToggleButtons::make('status')->inline()->required()
                    ->options(array_intersect_key(self::STATUSES, array_flip(self::FLOW[$record->status] ?? array_keys(self::STATUSES))))
                    ->colors(self::COLORS),
                Textarea::make('admin_note')->label('Note to customer')->rows(3)->default($record->admin_note),
            ])
            ->action(fn (SupportRequest $record, array $data) => $record->update($data));
    }
}
