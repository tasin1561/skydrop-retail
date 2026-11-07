<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Demo staff account for the Filament admin panel. Set ADMIN_PASSWORD in .env and change it after first login.
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrNew(['email' => 'admin@skydrop.retail']);
        $user->forceFill([
            'name' => 'Skydrop Retail Admin',
            'password' => (string) config('retail.admin_password', 'change-me-now'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
