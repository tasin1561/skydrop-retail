<?php

namespace Tests\Feature\Messaging;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $phone = '+8801712345678'): User
    {
        return User::query()->create(['name' => 'Rahim', 'phone' => $phone]);
    }

    public function test_list_read_read_all_delete(): void
    {
        $u = $this->user();
        $other = $this->user('+8801812345678');
        foreach (range(1, 3) as $i) {
            UserNotification::query()->create(['user_id' => $u->id, 'type' => 'order_placed', 'title' => "N{$i}", 'icon' => 'order', 'link' => '/orders/SR-'.$i]);
        }
        $foreign = UserNotification::query()->create(['user_id' => $other->id, 'type' => 'welcome', 'title' => 'Other']);
        Sanctum::actingAs($u);

        $r = $this->getJson('/api/v1/notifications?perPage=2')->assertOk()
            ->assertJsonPath('unreadCount', 3)->assertJsonPath('total', 3)->assertJsonPath('hasMore', true)->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.title', 'N3')->assertJsonPath('items.0.read', false)->assertJsonPath('items.0.link', '/orders/SR-3');
        $id = $r->json('items.0.id');

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('item.read', true)->assertJsonPath('unreadCount', 2);
        $this->getJson('/api/v1/notifications?unread=1')->assertOk()->assertJsonPath('total', 2);
        $this->postJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$foreign->id}")->assertNotFound();

        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('marked', 2)->assertJsonPath('unreadCount', 0);
        $this->deleteJson("/api/v1/notifications/{$id}")->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('total', 2);
        $this->assertNull($foreign->refresh()->read_at);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/me/notifications')->assertUnauthorized();
    }

    public function test_preferences_and_locale(): void
    {
        Sanctum::actingAs($this->user());
        $this->getJson('/api/v1/me/notifications')->assertOk()->assertJsonPath('locale', 'en')
            ->assertJsonPath('marketing.sms', true)->assertJsonPath('emailVerified', false);
        $this->patchJson('/api/v1/me/notifications', ['locale' => 'bn', 'marketing' => ['sms' => false, 'push' => false]])->assertOk()
            ->assertJsonPath('locale', 'bn')->assertJsonPath('marketing.sms', false)->assertJsonPath('marketing.push', false)->assertJsonPath('marketing.email', true);
        $this->patchJson('/api/v1/me/notifications', ['locale' => 'fr'])->assertStatus(422);
    }

    public function test_push_tokens(): void
    {
        $u = $this->user();
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/push/expo-token', ['token' => 'nope'])->assertStatus(422);
        $this->postJson('/api/v1/push/expo-token', ['token' => 'ExponentPushToken[xyz]', 'platform' => 'ios'])->assertCreated();
        $this->postJson('/api/v1/push/expo-token', ['token' => 'ExponentPushToken[xyz]', 'platform' => 'ios'])->assertCreated();
        $this->assertSame(1, PushSubscription::query()->count());
        $this->postJson('/api/v1/push/subscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])
            ->assertCreated()->assertJsonPath('delivering', false);
        $this->deleteJson('/api/v1/push/expo-token', ['token' => 'ExponentPushToken[xyz]'])->assertOk();
        $this->assertSame(['web'], PushSubscription::query()->pluck('kind')->all());
    }

    public function test_email_verification_link(): void
    {
        Mail::fake();
        $u = $this->user();
        Sanctum::actingAs($u);
        $this->patchJson('/api/v1/me', ['email' => 'r@example.com'])->assertOk();
        $this->assertDatabaseHas('notifications_log', ['event' => 'email_verify', 'to' => 'r@example.com', 'status' => 'sent']);

        $url = URL::temporarySignedRoute('messaging.email.verify', now()->addDay(), ['user' => $u->id, 'hash' => sha1('r@example.com')]);
        $this->get($url)->assertRedirect('http://localhost:3000/account?email=verified');
        $this->assertNotNull($u->refresh()->email_verified_at);
        $this->getJson('/api/v1/me/notifications')->assertJsonPath('emailVerified', true);
        $this->postJson('/api/v1/me/email/verification')->assertOk()->assertJsonPath('verified', true);
    }
}
