<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Product data usage: every live retrieval call records its purpose, success and retry flag. Older rows are backfilled
// from the call type and HTTP status. Also grants the new page to the seeded Finance role (Super Admin and Manager
// already have it through "*").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('api_logs', 'purpose')) {
                $table->string('purpose', 20)->nullable()->after('type');
            }
            if (! Schema::hasColumn('api_logs', 'success')) {
                $table->boolean('success')->default(false)->after('status');
            }
            if (! Schema::hasColumn('api_logs', 'is_retry')) {
                $table->boolean('is_retry')->default(false)->after('success');
            }
            $table->index(['created_at', 'market'], 'api_logs_usage_index');
        });

        DB::table('api_logs')->where('provider', '!=', 'cache')->whereBetween('status', [200, 299])
            ->where(fn ($q) => $q->whereNull('error')->orWhere('error', ''))->update(['success' => true]);
        foreach (['search' => 'search', 'product' => 'product', 'bestsellers' => 'import', 'deals' => 'import'] as $type => $purpose) {
            DB::table('api_logs')->where('provider', '!=', 'cache')->where('type', $type)->whereNull('purpose')->update(['purpose' => $purpose]);
        }
        DB::table('api_logs')->where('error', 'hedge duplicate abandoned')->update(['is_retry' => true]);

        if (Schema::hasTable('roles')) {
            $role = DB::table('roles')->where('slug', 'finance')->first();
            $perms = $role ? (array) json_decode((string) $role->permissions, true) : null;
            if ($role && ! in_array('page.data-usage', $perms, true)) {
                $perms[] = 'page.data-usage';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($perms)), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropIndex('api_logs_usage_index');
            $table->dropColumn(['purpose', 'success', 'is_retry']);
        });
    }
};
