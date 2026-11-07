<?php

namespace App\Providers;

use App\Jobs\Fraud\ScoreOrderFraud;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Audit\ActivityLogger;
use App\Services\Audit\ActivityObserver;
use App\Services\Fraud\FraudGate;
use App\Services\Security\ClientIdentity;
use App\Support\Knobs;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Finance, fraud, audit and security wiring (kept out of the shared providers):
 * activity observers on key models, admin login events, the Filament action hook, fraud scoring on order placed,
 * client IP/device capture on orders, the admin password rule and activity-log retention.
 */
class OpsServiceProvider extends ServiceProvider
{
    /** Models whose staff edits are logged with a diff (only those that exist are observed). */
    public const AUDITED = [
        'App\\Models\\Order', 'App\\Models\\OrderItem', 'App\\Models\\Payment', 'App\\Models\\Coupon', 'App\\Models\\PricingRule',
        'App\\Models\\Setting', 'App\\Models\\User', 'App\\Models\\Product', 'App\\Models\\Category', 'App\\Models\\Marketplace',
        'App\\Models\\FxRate', 'App\\Models\\FxPaidRate', 'App\\Models\\PointsLedger', 'App\\Models\\Affiliate', 'App\\Models\\AffiliatePayout',
        'App\\Models\\AffiliateCommission', 'App\\Models\\Banner', 'App\\Models\\FlashSale', 'App\\Models\\RestrictedRule',
        'App\\Models\\BlockedEntry', 'App\\Models\\SupportRequest', 'App\\Models\\ProductReview', 'App\\Models\\MessageTemplate',
        'App\\Models\\GiftCard', 'App\\Models\\WalletLedger', 'App\\Models\\Role',
    ];

    public function boot(): void
    {
        foreach (self::AUDITED as $model) {
            if (class_exists($model)) {
                $model::observe(ActivityObserver::class);
            }
        }

        $this->orderHooks();
        $this->authEvents();
        $this->filamentActionHook();

        Password::defaults(fn () => Password::min((int) config('security.admin.password_min', 12))->letters()->mixedCase()->numbers()->symbols());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(function () {
                $days = (int) Knobs::get('security.activity.retention_days', 365);
                if ($days > 0) {
                    ActivityLog::query()->where('created_at', '<', now()->subDays($days))->delete();
                }
            })->daily()->name('activity-log-retention');
        });
    }

    private function orderHooks(): void
    {
        Order::creating(function (Order $order) {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }
            $request = request();
            $order->client_ip ??= ClientIdentity::ip($request);
            $device = (string) $request->header('X-Device-Id');
            $order->device_id ??= $device !== '' ? Str::limit($device, 64, '') : null;
        });

        Order::created(function (Order $order) {
            if (FraudGate::active() && ($order->getAttributes()['kind'] ?? 'goods') === 'goods') {
                ScoreOrderFraud::dispatch($order->id)->afterCommit();
            }
        });
    }

    private function authEvents(): void
    {
        Event::listen(Login::class, function (Login $e) {
            if ($e->guard === 'web') {
                ActivityLogger::log('login', null, [], 'Signed in to the admin panel', [], $e->user);
            }
        });
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->guard === 'web' && $e->user) {
                ActivityLogger::log('logout', null, [], 'Signed out', [], $e->user);
            }
        });
        Event::listen(Failed::class, function (Failed $e) {
            if ($e->guard === 'web') {
                ActivityLogger::log('login_failed', null, [], 'Failed admin sign-in for '.Str::limit((string) ($e->credentials['email'] ?? '?'), 120),
                    ['email' => $e->credentials['email'] ?? null], $e->user);
            }
        });
    }

    /** Every Filament action a staff member runs (status changes, refunds, points, exports…) → activity log. */
    private function filamentActionHook(): void
    {
        if (! function_exists('Livewire\\on')) {
            return;
        }
        \Livewire\on('call', function ($component, $method, $params) {
            if ($method !== 'callMountedAction' || ! property_exists($component, 'mountedActions') || ! ActivityLogger::isStaff()) {
                return null;
            }
            $mounted = $component->mountedActions ?? [];
            $action = end($mounted) ?: [];

            return function ($result = null) use ($component, $action, $mounted) {
                try {
                    $name = (string) ($action['name'] ?? 'unknown');
                    $recordKey = $action['context']['recordKey'] ?? null;
                    $subjectType = null;
                    if (method_exists($component, 'getRecord') && ($rec = rescue(fn () => $component->getRecord(), null, false))) {
                        $subjectType = $rec::class;
                        $recordKey ??= $rec->getKey();
                    } elseif (method_exists($component, 'getTable')) {
                        $subjectType = rescue(fn () => $component->getTable()->getModel(), null, false);
                    }
                    ActivityLogger::log('action:'.Str::limit($name, 50, ''), null, (array) ($action['data'] ?? []),
                        Str::headline($name).' on '.class_basename($component),
                        ['subject_type' => $subjectType, 'subject_id' => $recordKey, 'page' => $component::class,
                            'records' => count($mounted) > 1 ? null : ($action['context']['bulk'] ?? null)]);
                } catch (Throwable) {
                    // never break the action
                }

                return $result;
            };
        });
    }
}
