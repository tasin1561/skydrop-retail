<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Customer sign-ins expire after 90 days. */
class TokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_login_token_expires_after_90_days(): void
    {
        $phone = '+8801712345678';
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone])->json('demoCode');
        $res = $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => $code, 'name' => 'Expiry Test'])->assertOk();

        $token = PersonalAccessToken::findToken($res->json('token'));
        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertNotNull($res->json('expiresAt'));

        $auth = ['Authorization' => 'Bearer '.$res->json('token')];
        $this->getJson('/api/v1/me', $auth)->assertOk();

        $this->travel(91)->days();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', $auth)->assertUnauthorized();
    }

    public function test_expired_tokens_are_pruned(): void
    {
        $user = User::factory()->create();
        $user->createToken('web', ['shopper'], now()->subDays(2));
        $user->createToken('web', ['shopper'], now()->addDays(30));
        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->assertSame(1, $user->tokens()->count());
    }
}
