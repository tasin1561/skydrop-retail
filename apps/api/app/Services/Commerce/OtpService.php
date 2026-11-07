<?php

namespace App\Services\Commerce;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    /** @deprecated default of the "accounts.otp_per_phone_hour" limit (App\Support\Limits) */
    public const MAX_SENDS = 3;

    public const WINDOW = 3600; // seconds (limits are per hour)

    public function __construct(private SmsManager $sms, private PointsService $points) {}

    /** @return array{ok: bool, expiresIn: int, demoCode?: string} */
    public function request(string $rawPhone, ?string $ip): array
    {
        $phone = Phone::normalize($rawPhone) ?? throw CommerceException::field('phone', 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).');

        // System → Limits: OTP codes per phone per hour and per IP per hour.
        $limits = ['otp:phone:'.$phone => \App\Support\Limits::int('accounts.otp_per_phone_hour'), 'otp:ip:'.($ip ?? 'none') => \App\Support\Limits::int('accounts.otp_per_ip_hour')];
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = RateLimiter::availableIn($key);
                throw new CommerceException("Too many codes requested. Try again in {$wait} seconds.", 429, ['retryAfter' => $wait]);
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, self::WINDOW);
        }

        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('retail.otp.ttl', 300);

        DB::transaction(function () use ($phone, $code, $ttl, $ip) {
            // Only the newest code is valid.
            OtpCode::query()->where('phone', $phone)->whereNull('used_at')->update(['used_at' => now()]);
            OtpCode::query()->create([
                'phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addSeconds($ttl), 'ip' => $ip,
            ]);
        });

        // Messaging module: OTP template (user's language), sent synchronously through the OTP SMS chain, never queued as marketing.
        app(Messenger::class)->otp($phone, $code, $ttl, User::query()->where('phone', $phone)->first());

        $out = ['ok' => true, 'expiresIn' => $ttl];
        if (config('retail.otp.driver') === 'log') {
            $out['demoCode'] = $code;
        }

        return $out;
    }

    /**
     * Checks the code and signs the shopper in. A brand-new phone needs `name`; without it we answer 422
     * (errors.name) and leave the code valid so the client can resend with the name.
     *
     * @return array{user: User, isNew: bool}
     */
    public function verify(string $rawPhone, string $code, ?string $name): array
    {
        $phone = Phone::normalize($rawPhone) ?? throw CommerceException::field('phone', 'Enter a valid Bangladeshi mobile number.');

        $otp = OtpCode::query()->where('phone', $phone)->whereNull('used_at')->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast()) {
            throw CommerceException::field('code', 'This code has expired. Request a new one.');
        }
        $max = (int) config('retail.otp.max_attempts', 5);
        if ($otp->attempts >= $max) {
            $otp->update(['used_at' => now()]);
            throw CommerceException::field('code', 'Too many wrong attempts. Request a new code.', 429);
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');
            $left = $max - $otp->attempts;
            throw CommerceException::field('code', $left > 0 ? "Wrong code. {$left} attempt(s) left." : 'Too many wrong attempts. Request a new code.');
        }

        $user = User::query()->where('phone', $phone)->first();
        if ($user && $user->blocked_at) {
            $otp->update(['used_at' => now()]);
            throw new CommerceException('This account has been blocked. Please contact Skydrop Retail support.', 403, [], 'blocked');
        }
        $name = trim((string) $name);
        if (! $user && $name === '') {
            throw new CommerceException('Tell us your name to create your account.', 422, ['errors' => ['name' => ['Your name is required for a new account.']], 'needsName' => true]);
        }

        $isNew = false;
        DB::transaction(function () use (&$user, &$isNew, $otp, $phone, $name) {
            $otp->update(['used_at' => now()]);
            if (! $user) {
                $user = User::query()->create(['name' => mb_substr($name, 0, 80), 'phone' => $phone]);
                $isNew = true;
            }
            $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now()])->save();
        });

        if ($isNew) {
            $this->points->signupBonus($user);
            $user->refresh();
            app(Notifier::class)->welcome($user); // messaging module
        }

        return ['user' => $user, 'isNew' => $isNew];
    }
}
