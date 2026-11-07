<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_exactly_one_default_address(): void
    {
        $u = $this->shopper();
        $a = $this->postJson('/api/v1/addresses', $this->addressPayload())->assertCreated()
            ->assertJsonPath('address.isDefault', true)->assertJsonPath('address.phone', '+8801812345678')->json('address');
        $b = $this->postJson('/api/v1/addresses', $this->addressPayload(['area' => 'Other']))->assertCreated()
            ->assertJsonPath('address.isDefault', false)->json('address');

        $this->patchJson("/api/v1/addresses/{$b['id']}", ['isDefault' => true])->assertOk()->assertJsonPath('address.isDefault', true);
        $this->assertSame(1, $u->addresses()->where('is_default', true)->count());
        $this->assertTrue($u->addresses()->find($b['id'])->is_default);

        // Deleting the default promotes another.
        $this->deleteJson("/api/v1/addresses/{$b['id']}")->assertOk();
        $this->assertTrue($u->addresses()->find($a['id'])->is_default);

        $this->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_validates_division_and_district(): void
    {
        $this->shopper();
        $this->postJson('/api/v1/addresses', $this->addressPayload(['district' => 'Sylhet']))->assertStatus(422)->assertJsonValidationErrors('district');
        $this->postJson('/api/v1/addresses', $this->addressPayload(['division' => 'Atlantis']))->assertStatus(422)->assertJsonValidationErrors('division');
    }

    public function test_cannot_touch_other_users_address(): void
    {
        $other = $this->shopper(['phone' => '+8801911111111']);
        $a = $this->address($other);
        $this->shopper();
        $this->patchJson("/api/v1/addresses/{$a->id}", ['area' => 'X'])->assertNotFound();
    }
}
