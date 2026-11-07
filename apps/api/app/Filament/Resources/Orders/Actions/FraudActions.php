<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\Audit\ActivityLogger;
use App\Services\Fraud\FraudGate;
use App\Services\Fraud\FraudService;
use App\Services\Security\Blocklist;
use App\Support\AdminSecurity;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Order view actions for the fraud check (permission fraud.override). */
class FraudActions
{
    /** @return array<int, Action> */
    public static function record(): array
    {
        return [self::clear(), self::recheck(), self::blockPhone()];
    }

    public static function clear(): Action
    {
        return Action::make('fraudClear')->label('Clear fraud review')->icon(Heroicon::OutlinedShieldCheck)->color('warning')
            ->visible(fn (Order $record) => $record->fraud_review && AdminSecurity::can('fraud.override'))
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->rows(2)->helperText('Why this order is safe (e.g. called the customer).')])
            ->action(function (Order $record, array $data) {
                $record->forceFill(['fraud_review' => false, 'staff_notes' => trim(($record->staff_notes ? $record->staff_notes."\n" : '')
                    .'['.now()->format('d M H:i').'] Fraud review cleared: '.$data['reason'])])->save();
                ActivityLogger::log('fraud.override', $record, [], 'Cleared fraud review: '.$data['reason']);
                Notification::make()->title('Fraud review cleared; verify the order as usual.')->success()->send();
            });
    }

    public static function recheck(): Action
    {
        return Action::make('fraudRecheck')->label('Re-run fraud check')->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn () => FraudGate::active() && AdminSecurity::can('fraud.override'))
            ->action(function (Order $record) {
                $r = app(FraudService::class)->scoreOrder($record);
                Notification::make()->title("Fraud score {$r['score']} ({$r['level']})")->body(implode("\n", $r['reasons']) ?: null)->send();
            });
    }

    public static function blockPhone(): Action
    {
        return Action::make('fraudBlock')->label('Block customer phone')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (Order $record) => AdminSecurity::can('fraud.override') && filled($record->address['phone'] ?? null)
                && ! Blocklist::isBlocked('phone', (string) $record->address['phone']))
            ->requiresConfirmation()
            ->schema([Textarea::make('reason')->required()->rows(2)])
            ->action(function (Order $record, array $data) {
                Blocklist::block('phone', (string) $record->address['phone'], $data['reason'].' (order '.$record->id.')', 'fraud');
                Notification::make()->title('Phone blocked')->success()->send();
            });
    }
}
