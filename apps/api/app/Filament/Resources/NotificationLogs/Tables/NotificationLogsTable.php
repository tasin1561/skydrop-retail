<?php

namespace App\Filament\Resources\NotificationLogs\Tables;

use App\Filament\Support\Admin;
use App\Models\NotificationLog;
use App\Services\Messaging\Events;
use App\Services\Messaging\Messenger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationLogsTable
{
    public const STATUS_COLORS = ['queued' => 'gray', 'sent' => 'success', 'failed' => 'danger'];

    public static function configure(Table $table): Table
    {
        $events = Events::labels() + ['custom' => 'Staff message', 'broadcast' => 'Broadcast', 'admin_alert' => 'Admin alert'];

        return $table
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable()->dateTimeTooltip(),
                TextColumn::make('event')->formatStateUsing(fn ($state) => $events[$state] ?? $state)->description(fn (NotificationLog $r) => $r->category)->searchable(),
                TextColumn::make('channel')->badge()->color(fn ($state) => ['sms' => 'warning', 'email' => 'info', 'push' => 'success'][$state] ?? 'gray'),
                TextColumn::make('to')->label('To')->formatStateUsing(fn (NotificationLog $r) => $r->maskedTo())->searchable(),
                TextColumn::make('user.name')->label('Customer')->placeholder('—')->toggleable(),
                TextColumn::make('order_id')->label('Order')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('status')->badge()->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('provider')->placeholder('—')->toggleable(),
                TextColumn::make('segments')->label('SMS parts')->placeholder('—')->formatStateUsing(fn ($state) => $state ?: '—')->toggleable(),
                TextColumn::make('error')->limit(50)->placeholder('')->color('danger')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('channel')->options(['sms' => 'SMS', 'email' => 'Email', 'push' => 'Push']),
                SelectFilter::make('status')->options(['queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed']),
                SelectFilter::make('event')->options($events)->searchable(),
                SelectFilter::make('category')->options(['transactional' => 'Transactional', 'alert' => 'Alert', 'marketing' => 'Marketing', 'otp' => 'OTP', 'admin' => 'Admin', 'test' => 'Test']),
                Filter::make('today')->label('Last 24 hours')->query(fn (Builder $q) => $q->where('created_at', '>=', now()->subDay())),
            ])
            ->recordActions([
                ViewAction::make()->modalHeading(fn (NotificationLog $r) => ($events[$r->event] ?? $r->event).' · '.strtoupper($r->channel))
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('status')->badge()->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                            TextEntry::make('to')->state(fn (NotificationLog $r) => $r->maskedTo()),
                            TextEntry::make('locale'),
                            TextEntry::make('provider')->placeholder('—'),
                            TextEntry::make('provider_message_id')->label('Provider id')->placeholder('—')->copyable(),
                            TextEntry::make('attempts'),
                            TextEntry::make('sent_at')->dateTime()->placeholder('—'),
                            TextEntry::make('segments')->label('SMS parts'),
                            TextEntry::make('broadcast_id')->label('Broadcast')->placeholder('—'),
                        ]),
                        TextEntry::make('subject')->placeholder('—'),
                        TextEntry::make('body')->state(fn (NotificationLog $r) => $r->body)->extraAttributes(['style' => 'white-space:pre-wrap']),
                        TextEntry::make('error')->color('danger')->placeholder('—'),
                    ]),
                Action::make('resend')->icon(Heroicon::OutlinedArrowPath)->color('gray')->requiresConfirmation()
                    ->visible(fn (NotificationLog $r) => ! in_array($r->category, ['otp'], true) && Admin::role() !== null)
                    ->action(function (NotificationLog $r) {
                        $copy = app(Messenger::class)->resend($r);
                        Notification::make()->title('Queued again')->body('New log #'.$copy->id.' · '.$copy->refresh()->status)->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('resendFailed')->label('Resend selected')->icon(Heroicon::OutlinedArrowPath)->requiresConfirmation()
                    ->visible(fn () => Admin::canManage())
                    ->action(function (Collection $records) {
                        $n = 0;
                        foreach ($records->where('category', '!=', 'otp') as $r) {
                            app(Messenger::class)->resend($r);
                            $n++;
                        }
                        Notification::make()->title("{$n} message(s) queued again")->success()->send();
                    }),
            ]);
    }
}
