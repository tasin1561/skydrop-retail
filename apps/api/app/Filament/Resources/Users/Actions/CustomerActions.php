<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Support\Admin;
use App\Filament\Support\Ops;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class CustomerActions
{
    public static function notify(): Action
    {
        return Action::make('notify')
            ->label('Send SMS / notification')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->visible(fn () => Admin::can('customers.message'))
            ->schema([
                Radio::make('channel')->options(['sms' => 'SMS', 'push' => 'App notification', 'email' => 'Email'])->default('sms')->inline()->required(),
                Textarea::make('message')->required()->maxLength(480)->rows(4)->helperText('Keep SMS under 160 characters (Bangla: 70).'),
            ])
            ->action(function (User $record, array $data) {
                try {
                    $sent = Ops::notify($record, $data['message'], $data['channel']);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Sending failed')->body($e->getMessage())->danger()->send();

                    return;
                }
                $sent
                    ? Notification::make()->title('Message sent to '.$record->name)->success()->send()
                    : Notification::make()->title('Notifier not available yet')->body('App\Services\Commerce\Notifier is part of the commerce module.')->warning()->send();
            });
    }

    public static function adjustPoints(): Action
    {
        return Action::make('adjustPoints')
            ->label('Adjust points')
            ->icon(Heroicon::OutlinedGift)
            ->visible(fn () => Admin::can('customers.adjust_points'))
            ->schema([
                TextInput::make('points')->numeric()->integer()->required()->notIn([0])
                    ->helperText(fn (User $record) => 'Current balance: '.(int) $record->points_balance.'. Use a negative number to deduct.'),
                TextInput::make('note')->required()->maxLength(200),
            ])
            ->action(function (User $record, array $data) {
                try {
                    Ops::adjustPoints($record, (int) $data['points'], $data['note']);
                    Notification::make()->title('Points adjusted')->body('New balance: '.(int) $record->refresh()->points_balance)->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Adjustment failed')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label(fn (User $record) => $record->blocked_at ? 'Unblock' : 'Block')
            ->icon(fn (User $record) => $record->blocked_at ? Heroicon::OutlinedLockOpen : Heroicon::OutlinedNoSymbol)
            ->color(fn (User $record) => $record->blocked_at ? 'success' : 'danger')
            ->visible(fn () => Admin::can('customers.block'))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record) => $record->blocked_at
                ? 'The customer can sign in and order again.'
                : 'The customer is signed out everywhere and cannot sign in or place orders.')
            ->action(function (User $record) {
                $blocking = ! $record->blocked_at;
                $record->forceFill(['blocked_at' => $blocking ? now() : null])->save();
                if ($blocking && method_exists($record, 'tokens')) {
                    $record->tokens()->delete();
                }
                Notification::make()->title($record->name.($blocking ? ' blocked' : ' unblocked'))->success()->send();
            });
    }
}
