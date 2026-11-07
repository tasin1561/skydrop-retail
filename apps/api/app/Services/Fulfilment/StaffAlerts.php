<?php

namespace App\Services\Fulfilment;

use App\Models\User;
use App\Services\Commerce\Notifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/** Staff alerts: Filament database notification for every staff user + the ops email (Notifier::admin). */
class StaffAlerts
{
    public static function send(string $title, string $body, ?string $url = null, string $tone = 'danger'): void
    {
        rescue(function () use ($title, $body, $url, $tone) {
            if (! Schema::hasTable('notifications')) {
                return;
            }
            $n = Notification::make()->title($title)->body($body)->{$tone}();
            if ($url) {
                $n->actions([Action::make('open')->label('Open')->url($url)]);
            }
            $n->sendToDatabase(User::query()->where('is_admin', true)->get());
        }, null, true);

        if (method_exists(Notifier::class, 'admin')) {
            rescue(fn () => app(Notifier::class)->admin($title, $body.($url ? "\n\n".$url : '')), null, true);
        }
    }
}
