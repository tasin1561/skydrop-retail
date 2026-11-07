<?php

namespace App\Filament\Resources\TrackingEvents\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\TrackingEvent;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrackingEventsTable
{
    public const STATUS_COLORS = ['queued' => 'warning', 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'gray'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M, H:i:s')->sortable(),
                TextColumn::make('event')->badge()->color('gray')->sortable(),
                TextColumn::make('destination')->badge()->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray')->sortable(),
                TextColumn::make('response_code')->label('HTTP')->placeholder('—')->alignEnd(),
                TextColumn::make('order_id')->label('Order')->placeholder('—')->searchable()
                    ->url(fn (TrackingEvent $r) => $r->order_id ? OrderResource::getUrl('view', ['record' => $r->order_id]) : null),
                TextColumn::make('event_id')->label('Event id')->limit(24)->searchable()->copyable()->toggleable(),
                TextColumn::make('attempts')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error')->limit(50)->color('danger')->placeholder('—')->tooltip(fn (TrackingEvent $r) => $r->error)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(TrackingEvent::STATUSES, array_map('ucfirst', TrackingEvent::STATUSES)))->multiple(),
                SelectFilter::make('destination')->options(['delivro' => 'Delivro', 'meta' => 'Meta CAPI', 'ga4' => 'GA4', 'tiktok' => 'TikTok'])->multiple(),
                SelectFilter::make('event')->options(fn () => TrackingEvent::query()->distinct()->pluck('event', 'event')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
