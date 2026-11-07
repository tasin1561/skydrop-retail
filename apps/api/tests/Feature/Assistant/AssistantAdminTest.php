<?php

namespace Tests\Feature\Assistant;

use App\Filament\Pages\AssistantSettings as SettingsPage;
use App\Filament\Resources\AssistantConversations\AssistantConversationResource;
use App\Filament\Widgets\AssistantUsage;
use App\Models\Assistant\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantSettings;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = new User;
        $u->forceFill(['name' => 'Admin', 'email' => 'ai-admin@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();

        return $u;
    }

    public function test_admin_can_browse_conversations_with_masked_phone(): void
    {
        Features::set('ai_assistant', true);
        $shopper = User::query()->create(['name' => 'Rahim', 'phone' => '+8801712345678']);
        $c = AssistantConversation::query()->create(['user_id' => $shopper->id, 'ip_hash' => 'x', 'title' => 'earbuds', 'last_message_at' => now()]);
        $c->messages()->create(['role' => 'user', 'content' => 'earbuds under 3000']);
        $c->messages()->create(['role' => 'assistant', 'content' => 'Here you go', 'model' => 'claude-sonnet-5-5', 'cost_usd' => 0.004]);

        $this->actingAs($this->admin());
        $this->get(AssistantConversationResource::getUrl('index'))->assertOk()->assertSee('earbuds')->assertSee('+88017•••••678')->assertDontSee('+8801712345678');
        $this->get(AssistantConversationResource::getUrl('view', ['record' => $c]))->assertOk()->assertSee('Here you go')->assertDontSee('+8801712345678');
        Livewire::test(AssistantUsage::class)->assertOk();
    }

    public function test_settings_page_saves_settings_and_feature_switch(): void
    {
        $this->actingAs($this->admin());
        $this->get(SettingsPage::getUrl())->assertOk();
        Livewire::test(SettingsPage::class)
            ->set('data.enabled', true)
            ->set('data.model', 'claude-haiku-5-5')
            ->set('data.monthly_budget_usd', 25)
            ->set('data.prompts__en', "Earbuds\nShoes")
            ->call('save')->assertHasNoErrors();

        $this->assertTrue(Features::enabled('ai_assistant'));
        $this->assertSame('claude-haiku-5-5', AssistantSettings::get('model'));
        $this->assertSame(25.0, AssistantSettings::budget());
        $this->assertSame(['Earbuds', 'Shoes'], AssistantSettings::prompts('en'));
    }
}
