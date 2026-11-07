<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (! User::query()->where('email', 'test@example.com')->exists()) { // idempotent: db:seed can run again
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // ---- catalog module seeders (runs first: commerce data may reference products) ----
        $this->call([MarketplaceSeeder::class, CategorySeeder::class, CategoryTreeSeeder::class, PricingRuleSeeder::class, CatalogSeeder::class, CategorizeProductsSeeder::class]);
        // ---- end catalog module seeders ----

        // ---- commerce module seeders ----
        $this->call([CouponSeeder::class, AdminUserSeeder::class]);
        // ---- end commerce module seeders ----

        // ---- messaging module seeders ----
        $this->call([MessageTemplateSeeder::class]);
        // ---- end messaging module seeders ----

        // ---- money module seeders (example Skydrop Plus plans, inactive until the owner activates them) ----
        $this->call([MembershipPlanSeeder::class]);
        // ---- end money module seeders ----
    }
}
