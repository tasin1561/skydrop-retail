<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\OtpService;
use App\Services\Growth\ReferralService;
use App\Services\Security\ClientIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:25']]);

        // Per-IP OTP limit on the real client IP (CF-Connecting-IP when trusted), the same identity ApiShield uses.
        return response()->json($this->otp->request($data['phone'], ClientIdentity::ip($request)));
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'name' => ['nullable', 'string', 'max:80'],
            'device' => ['nullable', 'string', 'max:60'],
            'invite' => ['nullable', 'string', 'max:20'], // refer-a-friend code (growth module)
        ]);
        $r = $this->otp->verify($data['phone'], $data['code'], $data['name'] ?? null);
        if ($r['isNew'] && ! empty($data['invite'])) {
            rescue(fn () => app(ReferralService::class)->attachInvite($r['user'], $data['invite']), null, true);
        }
        $token = $r['user']->createToken($data['device'] ?? 'web', ['shopper'])->plainTextToken;

        return response()->json(['token' => $token, 'user' => $r['user']->toApi(), 'isNew' => $r['isNew']]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->refresh()->toApi()]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['sometimes', 'in:en,bn'], // storefront language; picks the en/bn SMS/email templates
        ]);
        if (isset($data['locale'])) {
            $user->forceFill(['locale' => $data['locale']]);
            unset($data['locale']);
        }
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        $user->fill($data)->save();

        return response()->json(['user' => $user->toApi()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
