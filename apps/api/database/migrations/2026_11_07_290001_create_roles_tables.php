<?php

use App\Support\Rbac\DefaultRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Staff RBAC: roles with permission keys, many-to-many with users. Seeds the default roles and moves the legacy
// users.role values (admin / manager / support / hub) onto them. users.role stays as a fallback.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->string('slug', 60)->unique();
                $table->string('description', 500)->nullable();
                $table->boolean('is_system')->default(false);
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['role_id', 'user_id']);
            });
        }

        $now = now();
        foreach (DefaultRoles::all() as $slug => $def) {
            if (DB::table('roles')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('roles')->insert([
                'name' => $def['name'], 'slug' => $slug, 'description' => $def['description'],
                'is_system' => true, 'permissions' => json_encode($def['permissions']), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Data migration: legacy role string → role pivot.
        $ids = DB::table('roles')->pluck('id', 'slug');
        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')->where('is_admin', true)->orderBy('id')->select(['id', 'role'])->each(function ($u) use ($ids, $now) {
                $slug = DefaultRoles::LEGACY[$u->role ?: 'admin'] ?? null;
                if ($slug && isset($ids[$slug]) && ! DB::table('role_user')->where('user_id', $u->id)->exists()) {
                    DB::table('role_user')->insert(['role_id' => $ids[$slug], 'user_id' => $u->id, 'created_at' => $now, 'updated_at' => $now]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
