<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\Prefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/** GET|PATCH /me/notifications, email verification and one-click unsubscribe. */
class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()->refresh()));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'locale' => ['sometimes', Rule::in(['en', 'bn'])],
            'marketing' => ['sometimes', 'array'],
            'marketing.sms' => ['sometimes', 'boolean'],
            'marketing.email' => ['sometimes', 'boolean'],
            'marketing.push' => ['sometimes', 'boolean'],
            'marketing.inapp' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['locale'])) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }
        if (isset($data['marketing'])) {
            Prefs::set($user, $data['marketing']);
        }

        return response()->json($this->payload($user->refresh()));
    }

    /** POST /me/email/verification — (re)send the verification link. */
    public function sendVerification(Request $request, Notifier $notifier): JsonResponse
    {
        $user = $request->user();
        if (! $user->email) {
            return response()->json(['message' => 'Add an email address first.', 'errors' => ['email' => ['Add an email address first.']]], 422);
        }
        if ($user->email_verified_at) {
            return response()->json(['ok' => true, 'verified' => true]);
        }
        $key = 'email-verify:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'Please wait before requesting another link.', 'retryAfter' => RateLimiter::availableIn($key)], 429);
        }
        RateLimiter::hit($key, 3600);
        $notifier->emailVerification($user);

        return response()->json(['ok' => true, 'verified' => false]);
    }

    /** GET /email/verify/{user}/{hash} (signed) — browser link from the email; redirects to the storefront. */
    public function verify(Request $request, int $user, string $hash)
    {
        $u = User::query()->findOrFail($user);
        $ok = $u->email && hash_equals(sha1((string) $u->email), $hash);
        if ($ok && ! $u->email_verified_at) {
            $u->forceFill(['email_verified_at' => now()])->save();
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok], $ok ? 200 : 422);
        }

        return redirect()->away(rtrim((string) config('retail.web_url'), '/').'/account?email='.($ok ? 'verified' : 'invalid'));
    }

    /** GET|POST /unsubscribe/{user}/{channel} (signed) — marketing opt-out from the email footer / List-Unsubscribe. */
    public function unsubscribe(Request $request, int $user, string $channel)
    {
        abort_unless(in_array($channel, Prefs::CHANNELS, true), 404);
        $u = User::query()->findOrFail($user);
        Prefs::set($u, [$channel => false]);
        if ($request->isMethod('post') || $request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return response(view('emails.unsubscribed', ['channel' => $channel, 'webUrl' => rtrim((string) config('retail.web_url'), '/')]));
    }

    private function payload(User $user): array
    {
        return [
            'locale' => Prefs::locale($user),
            'marketing' => Prefs::of($user)['marketing'],
            'email' => $user->email,
            'emailVerified' => $user->email_verified_at !== null,
            'transactional' => 'Order, payment and delivery updates are always sent.',
        ];
    }
}
