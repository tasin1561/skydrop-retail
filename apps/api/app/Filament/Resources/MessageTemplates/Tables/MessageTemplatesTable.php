<?php

namespace App\Filament\Resources\MessageTemplates\Tables;

use App\Models\MessageTemplate;
use App\Services\Messaging\Events;
use App\Services\Messaging\SmsSegments;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MessageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->defaultGroup('key')
            ->columns([
                TextColumn::make('key')->label('Event')->formatStateUsing(fn ($state) => Events::labels()[$state] ?? $state)
                    ->description(fn (MessageTemplate $r) => $r->key)->searchable()->sortable(),
                TextColumn::make('channel')->badge()->color(fn ($state) => ['sms' => 'warning', 'email' => 'info', 'push' => 'success'][$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => MessageTemplate::CHANNELS[$state] ?? $state),
                TextColumn::make('locale')->label('Lang')->badge()->color('gray'),
                TextColumn::make('subject')->limit(40)->placeholder('—')->searchable(),
                TextColumn::make('body')->limit(70)->searchable()->wrap(),
                TextColumn::make('segments')->label('SMS parts')->state(fn (MessageTemplate $r) => $r->channel === 'sms' ? SmsSegments::count($r->body) : null)->placeholder('—'),
                ToggleColumn::make('active'),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')->options(MessageTemplate::CHANNELS),
                SelectFilter::make('locale')->options(MessageTemplate::LOCALES),
                SelectFilter::make('key')->label('Event')->options(Events::labels()),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
