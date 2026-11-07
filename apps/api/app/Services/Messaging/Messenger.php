<?php

namespace App\Services\Messaging;

use App\Jobs\Messaging\DeliverMessage;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Commerce\Phone;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Commerce\Sms\SmsResult;
use App\Services\Messaging\Push\PushSender;
use App\Support\Features;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Renders templates and queues deliveries (one notifications_log row + one DeliverMessage job per channel),
 * and writes the in-app notification. Never throws to the caller.
 */
class Messenger
{
    public const CHANNELS = ['sms', 'email', 'push'];

    public function __construct(private TemplateRenderer $templates, private PushSender $push, private SmsManager $sms) {}

    /**
     * Sends a catalogued event to a user.
     *
     * @param  array{phone?: ?string, order_id?: ?string, link?: ?string, image?: ?string, blocks?: array, cta?: array, channels?: list<string>, inapp?: bool, data?: array}  $opts
     * @return list<NotificationLog>
     */
    public function event(User $user, string $event, array $data, array $opts = []): array
    {
        try {
            return $this->doEvent($user, $event, $data, $opts);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Free text (staff message, broadcast). $channels ⊆ sms, email, push, inapp.
     *
     * @return list<NotificationLog>
     */
    public function raw(User $user, array $channels, string $subject, string $body, string $category = 'transactional', array $opts = []): array
    {
        $logs = [];
        $event = $opts['event'] ?? ($category === 'marketing' ? 'broadcast' : 'custom');
        $marketing = $category === 'marketing';
        try {
            foreach (array_intersect(self::CHANNELS, $channels) as $channel) {
                if (! self::channelEnabled($channel, $category) || ($marketing && ! Prefs::allowsMarketing($user, $channel))) {
                    continue;
                }
                $to = $this->recipient($user, $channel, $opts['phone'] ?? null);
                if (! $to) {
                    continue;
                }
                $logs[] = $this->queue($user, $event, $category, $channel, Prefs::locale($user), $to, $subject, $body,
                    $this->meta($channel, $opts, $marketing, $user), $opts);
            }
            if (in_array('inapp', $channels, true) && self::channelEnabled('inapp', $category) && (! $marketing || Prefs::allowsMarketing($user, 'inapp'))) {
                $this->inApp($user, $event, $subject, $body, $opts + ['icon' => $marketing ? 'megaphone' : 'info']);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $logs;
    }

    /** OTP: SMS only, sent synchronously through the OTP chain, logged. */
    public function otp(string $phone, string $code, int $ttlSeconds, ?User $user = null): SmsResult
    {
        $data = ['code' => $code, 'minutes' => intdiv($ttlSeconds, 60)];
        $r = $this->templates->render('otp', 'sms', Prefs::locale($user), $data);
        $text = $r['body'] ?? "Your Skydrop Retail code is {$code}. It expires in {$data['minutes']} minutes. Never share it.";
        $result = $this->sms->send($phone, $text, $this->sms->otpChain());
        rescue(fn () => NotificationLog::query()->create([
            'user_id' => $user?->id, 'event' => 'otp', 'category' => 'otp', 'channel' => 'sms', 'locale' => $r['locale'] ?? 'en',
            'to' => Phone::normalize($phone) ?? $phone, 'body' => str_replace($code, '••••••', $text), // never store the code
            'status' => $result->ok ? 'sent' : 'failed', 'provider' => $result->driver, 'provider_message_id' => $result->messageId,
            'error' => $result->error, 'segments' => SmsSegments::count($text), 'attempts' => 1, 'sent_at' => $result->ok ? now() : null,
        ]), null, false);

        return $result;
    }

    /** Ops email to ALERT_EMAIL (and logged). */
    public function admin(string $subject, string $text): ?NotificationLog
    {
        $to = config('retail.alerts.email');
        if (! $to) {
            Log::info('[messaging:admin] '.$subject.': '.$text);

            return null;
        }
        try {
            return $this->queue(null, 'admin_alert', 'admin', 'email', 'en', $to, '[Skydrop Retail] '.$subject, $text, ['admin' => true], []);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Admin "send test": renders a template with sample data and delivers it now (synchronously). */
    public function test(MessageTemplate $t, string $to): ?NotificationLog
    {
        $data = (Events::get($t->key)['sample'] ?? []) + ['name' => 'Rahim'];
        $user = null;
        if ($t->channel === 'push') {
            $user = ctype_digit($to) ? User::query()->find((int) $to) : User::query()->where('phone', Phone::normalize($to))->first();
            if (! $user) {
                return null;
            }
            $to = 'user:'.$user->id;
        } elseif ($t->channel === 'sms') {
            $to = Phone::normalize($to);
            if (! $to) {
                return null;
            }
        }
        $subject = $t->subject !== null ? $this->templates->string($t->subject, $data) : null;
        $body = $this->templates->string($t->body, $data);
        $log = NotificationLog::query()->create([
            'user_id' => $user?->id, 'event' => $t->key, 'category' => 'test', 'channel' => $t->channel, 'locale' => $t->locale, 'to' => $to,
            'subject' => $t->channel === 'email' ? '[Test] '.$subject : $subject, 'body' => $body,
            'meta' => $t->channel === 'email' ? ['cta' => ['label' => 'View details', 'url' => self::webUrl('/')]] : null,
            'status' => 'queued', 'segments' => $t->channel === 'sms' ? SmsSegments::count($body) : 0,
        ]);
        dispatch_sync(new DeliverMessage($log->id));

        return $log->refresh();
    }

    /** Re-queues a copy of a logged message (admin "Resend"). */
    public function resend(NotificationLog $log): NotificationLog
    {
        $copy = $log->replicate(['provider', 'provider_message_id', 'error', 'sent_at', 'attempts']);
        $copy->forceFill(['status' => 'queued', 'attempts' => 0, 'meta' => ($log->meta ?? []) + ['resend_of' => $log->id]])->save();
        // Staff resends go out now, even in marketing quiet hours.
        dispatch((new DeliverMessage($copy->id))->onQueue(config('messaging.queue.transactional')))->afterCommit();

        return $copy;
    }

    /** True when a marketing message should wait (quiet hours, Asia/Dhaka). Returns the time to send at, or null. */
    public static function quietUntil(?Carbon $at = null): ?Carbon
    {
        $q = MessagingSettings::get('quiet_hours', config('messaging.quiet_hours'));
        $tz = $q['timezone'] ?? 'Asia/Dhaka';
        $now = ($at ?? now())->copy()->setTimezone($tz);
        [$sh, $sm] = array_map('intval', explode(':', $q['start'] ?? '22:00'));
        [$eh, $em] = array_map('intval', explode(':', $q['end'] ?? '09:00'));
        $start = $now->copy()->setTime($sh, $sm);
        $end = $now->copy()->setTime($eh, $em);
        $mins = $now->hour * 60 + $now->minute;
        $s = $sh * 60 + $sm;
        $e = $eh * 60 + $em;
        if ($s === $e) {
            return null;
        }
        $inside = $s > $e ? ($mins >= $s || $mins < $e) : ($mins >= $s && $mins < $e);
        if (! $inside) {
            return null;
        }
        if ($s > $e && $mins >= $s) {
            $end->addDay();
        }

        return $end->setTimezone(config('app.timezone'));
    }

    // ---------------------------------------------------------------------------------------------

    private function doEvent(User $user, string $event, array $data, array $opts): array
    {
        $def = Events::get($event) ?? ['category' => 'transactional', 'icon' => 'info'];
        $category = $def['category'];
        $marketing = $category === 'marketing';
        $locale = Prefs::locale($user);
        $data += ['name' => $this->firstName($user)];
        $logs = [];

        foreach (array_intersect(self::CHANNELS, $opts['channels'] ?? self::CHANNELS) as $channel) {
            if (! self::channelEnabled($channel, $category) || ($marketing && ! Prefs::allowsMarketing($user, $channel))) {
                continue;
            }
            $to = $this->recipient($user, $channel, $opts['phone'] ?? null);
            if (! $to) {
                continue;
            }
            $r = $this->templates->render($event, $channel, $locale, $data);
            if (! $r || $r['body'] === '') {
                continue;
            }
            $logs[] = $this->queue($user, $event, $category, $channel, $r['locale'], $to, $r['subject'], $r['body'],
                $this->meta($channel, $opts, $marketing, $user), $opts);
        }

        if (($opts['inapp'] ?? true) && self::channelEnabled('inapp', $category) && (! $marketing || Prefs::allowsMarketing($user, 'inapp'))) {
            // The in-app item uses the push template (even if push itself is switched off), else the email one.
            $t = $this->templates->find($event, 'push', $locale) ?? $this->templates->find($event, 'email', $locale);
            if ($t) {
                $title = $this->templates->string((string) ($t['subject'] ?? $def['label'] ?? $event), $data);
                $body = $this->templates->string($t['body'], $data);
                $this->inApp($user, $event, $title, $body, $opts + ['icon' => $def['icon']]);
            }
        }

        return $logs;
    }

    private function queue(?User $user, string $event, string $category, string $channel, string $locale, string $to, ?string $subject, string $body, array $meta, array $opts): NotificationLog
    {
        $log = NotificationLog::query()->create([
            'user_id' => $user?->id, 'order_id' => $opts['order_id'] ?? null, 'event' => $event, 'category' => $category,
            'channel' => $channel, 'locale' => $locale, 'to' => $to, 'subject' => $subject, 'body' => $body, 'meta' => $meta ?: null,
            'status' => 'queued', 'segments' => $channel === 'sms' ? SmsSegments::count($body) : 0,
            'broadcast_id' => $opts['broadcast_id'] ?? null,
        ]);
        self::dispatchLog($log);

        return $log;
    }

    public static function dispatchLog(NotificationLog $log): void
    {
        $job = new DeliverMessage($log->id);
        $queue = config('messaging.queue.'.($log->category === 'marketing' ? 'marketing' : ($log->category === 'otp' ? 'otp' : 'transactional')));
        $job->onQueue($queue);
        if ($log->category === 'marketing' && ($until = self::quietUntil())) {
            $job->delay($until);
        }
        dispatch($job)->afterCommit();
    }

    /** Admin feature switches (App\Support\Features): channel + marketing. OTP, admin alerts and staff tests bypass these. */
    public static function channelEnabled(string $channel, string $category = 'transactional'): bool
    {
        if (in_array($category, ['otp', 'admin', 'test'], true)) {
            return true;
        }
        if ($category === 'marketing' && ! Features::enabled('marketing_messages')) {
            return false;
        }

        return Features::enabled(match ($channel) {
            'inapp' => 'in_app_notifications',
            default => $channel,
        });
    }

    private function recipient(User $user, string $channel, ?string $phone): ?string
    {
        return match ($channel) {
            'sms' => Phone::normalize($phone) ?? Phone::normalize($user->phone),
            'email' => $user->email ?: null,
            'push' => $this->push->hasDevices($user) ? 'user:'.$user->id : null,
            default => null,
        };
    }

    private function meta(string $channel, array $opts, bool $marketing, ?User $user): array
    {
        $link = $opts['link'] ?? null;

        return match ($channel) {
            'email' => array_filter([
                'blocks' => $opts['blocks'] ?? null,
                'cta' => $opts['cta'] ?? ($link ? ['label' => 'View details', 'url' => self::webUrl($link)] : null),
                'image' => $opts['image'] ?? null,
                'marketing' => $marketing ?: null,
                'unsubscribe' => $marketing && $user ? URL::signedRoute('messaging.unsubscribe', ['user' => $user->id, 'channel' => 'email']) : null,
            ]),
            'push' => ['data' => array_filter(($opts['data'] ?? []) + ['link' => $link, 'route' => self::appRoute($link), 'image' => $opts['image'] ?? null])],
            default => [],
        };
    }

    private function inApp(User $user, string $type, string $title, string $body, array $opts): void
    {
        UserNotification::query()->create([
            'user_id' => $user->id, 'type' => $type, 'title' => mb_substr($title, 0, 190), 'body' => $body,
            'icon' => $opts['icon'] ?? 'info', 'link' => $opts['link'] ?? null, 'image' => $opts['image'] ?? null,
            'data' => array_filter(($opts['data'] ?? []) + ['orderId' => $opts['order_id'] ?? null]) ?: null,
        ]);
    }

    private function firstName(User $user): string
    {
        $n = trim((string) $user->name);

        return $n === '' ? 'there' : explode(' ', $n)[0];
    }

    /** Absolute storefront URL for a web path. */
    public static function webUrl(string $pathOrUrl): string
    {
        return str_starts_with($pathOrUrl, 'http') ? $pathOrUrl : rtrim((string) config('retail.web_url'), '/').'/'.ltrim($pathOrUrl, '/');
    }

    /** Mobile (expo-router) route for a web path: /orders/{id} → /order/{id}, /p/{id} → /product/{id}. */
    public static function appRoute(?string $link): ?string
    {
        if (! $link) {
            return null;
        }
        $path = parse_url($link, PHP_URL_PATH) ?: $link;

        return match (true) {
            (bool) preg_match('#^/orders/([^/]+)#', $path, $m) => '/order/'.$m[1],
            (bool) preg_match('#^/p/([^/]+)#', $path, $m) => '/product/'.$m[1],
            $path === '/cart' => '/cart',
            str_starts_with($path, '/account/points') => '/points',
            default => '/notifications',
        };
    }
}
