<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Throwable;

/** Admin helpers for the money module (wallet, gift cards, Skydrop Plus). */
class MoneyAdmin
{
    public const GROUP = 'Wallet & Plus';

    /** RBAC permission (App\Providers\MoneyServiceProvider::PERMISSIONS) with a manager fallback. */
    public static function can(string $key): bool
    {
        if (method_exists(Admin::class, 'can')) {
            return Admin::can($key);
        }

        return Admin::canManage();
    }

    public static function customerSelect(string $name = 'user_id'): Select
    {
        return Select::make($name)->label('Customer')->required()->searchable()
            ->getSearchResultsUsing(fn (string $search) => User::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} · {$u->phone}"])->all())
            ->getOptionLabelUsing(fn ($value) => ($u = User::query()->find($value)) ? "{$u->name} · {$u->phone}" : null);
    }

    public static function run(callable $fn, string $success): void
    {
        try {
            $fn();
            Notification::make()->title($success)->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Action failed')->body($e->getMessage())->danger()->persistent()->send();
        }
    }
}
