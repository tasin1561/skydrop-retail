<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// System module: grouped exceptions (Admin → System → Health → Errors) and worker / scheduler heartbeats.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('error_events')) {
            Schema::create('error_events', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint', 40)->unique(); // sha1(class|file|line)
                $table->string('class', 190);
                $table->text('message');
                $table->string('file', 300)->nullable();
                $table->unsignedInteger('line')->nullable();
                $table->string('url', 500)->nullable();
                $table->unsignedInteger('count')->default(1);
                $table->timestamp('first_seen_at');
                $table->timestamp('last_seen_at')->index();
            });
        }

        if (! Schema::hasTable('system_heartbeats')) {
            Schema::create('system_heartbeats', function (Blueprint $table) {
                $table->string('name', 40)->primary(); // worker | scheduler
                $table->timestamp('beat_at');
                $table->json('meta')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('error_events');
    }
};
