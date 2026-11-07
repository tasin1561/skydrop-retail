<?php

namespace Tests\Feature\Commerce;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        RateLimiter::clear('otp:phone:+8801712345678');
        RateLimiter::clear('otp:ip:127.0.0.1');
    }

    public function test_full_otp_flow_creates_user_and_token(): void
    {
        $r = $this->postJson('/api/v1/auth/otp/request', ['phone' => '017-1234-5678'])->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('expiresIn', 300);
        $code = $r->json('demoCode');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $code]);

        // New user without a name: 422 errors.name, code stays valid.
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+8801712345678', 'code' => $code])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $v = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => $code, 'name' => 'Rahim'])
            ->assertOk()->assertJsonPath('isNew', true)->assertJsonPath('user.phone', '+8801712345678');
        $token = $v->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame(100, User::query()->first()->points_balance); // signup bonus

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.name', 'Rahim');
        $this->withToken($token)->patchJson('/api/v1/me', ['email' => 'r@example.com'])->assertOk()->assertJsonPath('user.email', 'r@example.com');

        // Code is single-use.
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => $code])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_existing_user_signs_in_without_name(): void
    {
        User::query()->create(['name' => 'Old', 'phone' => '+8801712345678']);
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '8801712345678'])->json('demoCode');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => $code])->assertOk()->assertJsonPath('isNew', false);
    }

    public function test_blocked_user_cannot_sign_in_or_order(): void
    {
        $u = User::query()->create(['name' => 'Bad', 'phone' => '+8801712345678']);
        $u->forceFill(['blocked_at' => now()])->save();
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->json('demoCode');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => $code])->assertForbidden()->assertJsonPath('code', 'blocked');

        Sanctum::actingAs($u->refresh());
        $p = $this->product('A1', 1000);
        $this->postJson('/api/v1/orders', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'address' => $this->addressPayload(), 'payment' => 'bkash'])->assertForbidden()->assertJsonPath('code', 'blocked');
    }

    public function test_rejects_invalid_phone(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01212345678'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_wrong_code_counts_attempts_and_locks(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => '000000', 'name' => 'X'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => '000000', 'name' => 'X'])->assertStatus(429);
    }

    public function test_rate_limit_three_per_ten_minutes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->assertOk();
        }
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->assertStatus(429);
    }

    public function test_demo_code_hidden_with_real_driver(): void
    {
        config(['retail.otp.driver' => 'http', 'retail.sms.http.url' => null]);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->assertOk()->assertJsonMissingPath('demoCode');
    }
}
