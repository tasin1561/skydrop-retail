<?php

namespace App\Jobs\Messaging;

use App\Mail\BrandedMessage;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Commerce\Phone;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\Push\PushSender;
use App\Support\Limits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/** Delivers one notifications_log row over its channel. Retries with backoff; never throws into the caller. */
class DeliverMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $logId) {}

    public function handle(SmsManager $sms, PushSender $push): void
    {
        $log = NotificationLog::query()->find($this->logId);
        if (! $log || $log->status === 'sent') {
            return;
        }
        $log->attempts++;

        try {
            [$ok, $provider, $id, $error, $retryable] = match ($log->channel) {
                'sms' => $this->sms($sms, $log),
                'email' => $this->email($log),
                'push' => $this->push($push, $log),
                default => [false, null, null, 'Unknown channel', false],
            };
        } catch (Throwable $e) {
            report($e);
            [$ok, $provider, $id, $error, $retryable] = [false, null, null, $e->getMessage(), true];
        }

        if ($ok) {
            $log->forceFill(['status' => 'sent', 'provider' => $provider, 'provider_message_id' => $id, 'error' => null, 'sent_at' => now()])->save();

            return;
        }

        $last = ! $retryable || $this->job === null || $this->job instanceof SyncJob || $this->attempts() >= $this->tries;
        $log->forceFill(['status' => $last ? 'failed' : 'queued', 'provider' => $provider, 'error' => Str::limit((string) $error, 1000)])->save();
        if (! $last) {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        }
    }

    private function sms(SmsManager $sms, NotificationLog $log): array
    {
        $phone = Phone::normalize($log->to);
        if (! $phone) {
            return [false, null, null, 'Invalid phone number', false];
        }
        // Per-phone rate limit (OTP is sent elsewhere and is not counted here).
        $key = 'sms:phone:'.$phone;
        $max = Limits::int('messaging.sms_per_phone_hour'); // same setting as messaging.sms.per_phone_per_hour
        if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
            $wait = RateLimiter::availableIn($key);
            if ($this->job && ! $this->job instanceof SyncJob && $log->category !== 'marketing') {
                $this->release($wait + 5); // transactional: wait for the window

                return [false, null, null, "Rate limited for {$wait}s", true];
            }

            return [false, null, null, 'Rate limited: too many SMS to this phone in the last hour', false];
        }
        // System → Limits: total marketing SMS per day across all customers.
        $dailyMarketing = Limits::int('messaging.marketing_sms_per_day');
        if ($log->category === 'marketing' && $dailyMarketing > 0 && NotificationLog::query()->where('channel', 'sms')
            ->where('category', 'marketing')->where('status', 'sent')->where('sent_at', '>=', now()->startOfDay())->count() >= $dailyMarketing) {
            return [false, null, null, 'Daily marketing SMS limit reached', false];
        }
        RateLimiter::hit($key, 3600);
        $r = $sms->send($phone, $log->body);

        return [$r->ok, $r->driver, $r->messageId, $r->error, $r->retryable];
    }

    private function email(NotificationLog $log): array
    {
        if (! filter_var($log->to, FILTER_VALIDATE_EMAIL)) {
            return [false, null, null, 'Invalid email address', false];
        }
        $user = $log->user_id ? User::query()->find($log->user_id) : null;
        Mail::to($log->to, $user?->name)->send(new BrandedMessage($log));

        return [true, (string) config('mail.default'), null, null, false];
    }

    private function push(PushSender $push, NotificationLog $log): array
    {
        $user = $log->user_id ? User::query()->find($log->user_id) : null;
        if (! $user) {
            return [false, null, null, 'No user', false];
        }
        $r = $push->toUser($user, (string) ($log->subject ?? config('messaging.brand.name')), $log->body,
            ($log->meta['data'] ?? []) + ['type' => $log->event, 'logId' => $log->id]);
        if ($r->sent === 0 && $r->failed === 0) {
            return [false, 'expo', null, 'No registered devices', false];
        }

        return [$r->ok(), 'expo', $r->ids[0] ?? null, $r->errors ? implode('; ', array_unique($r->errors)) : null, ! $r->ok()];
    }

    public function failed(?Throwable $e): void
    {
        NotificationLog::query()->whereKey($this->logId)->where('status', '!=', 'sent')
            ->update(['status' => 'failed', 'error' => Str::limit((string) $e?->getMessage(), 1000)]);
    }
}
