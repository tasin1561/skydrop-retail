<?php

namespace Tests\Feature\Money;

use App\Models\MembershipPlan;
use Database\Seeders\MembershipPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_three_inactive_example_plans_idempotently(): void
    {
        $this->seed(MembershipPlanSeeder::class);
        $plans = MembershipPlan::query()->orderBy('sort')->get();
        $this->assertSame(['plus-monthly', 'plus-quarterly', 'plus-yearly'], $plans->pluck('slug')->all());
        $this->assertEquals([299, 799, 2499], $plans->pluck('price_bdt')->all());
        $this->assertSame([1, 3, 12], $plans->map->months()->all());
        $this->assertFalse($plans->contains('active', true));
        $this->assertTrue($plans->last()->benefits['free_shipping']);

        // Owner activates and edits one; seeding again changes nothing and adds nothing.
        $plans->first()->update(['active' => true, 'price_bdt' => 349]);
        $this->seed(MembershipPlanSeeder::class);
        $this->assertSame(3, MembershipPlan::query()->count());
        $first = MembershipPlan::query()->where('slug', 'plus-monthly')->first();
        $this->assertTrue($first->active);
        $this->assertEquals(349, $first->price_bdt);
    }
}
