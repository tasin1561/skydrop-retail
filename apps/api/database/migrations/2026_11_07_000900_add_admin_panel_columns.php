<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin panel: staff roles, customer blocking and internal order notes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->nullable(); // admin | manager | support (staff only; null on an is_admin user = admin)
            }
            if (! Schema::hasColumn('users', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'staff_notes')) {
                $table->text('staff_notes')->nullable(); // internal, never shown to the shopper
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('staff_notes'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'blocked_at']));
    }
};
