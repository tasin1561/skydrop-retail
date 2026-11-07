<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Staff activity log (App\Services\Audit\ActivityLogger). Also records security events (honeypot hits, blocks).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index(); // staff member (null = system / anonymous)
            $table->string('user_name', 120)->nullable();       // snapshot, survives user deletion
            $table->string('action', 60)->index();              // created | updated | deleted | action:<name> | login | logout | login_failed | export | security.*
            $table->string('subject_type', 120)->nullable()->index();
            $table->string('subject_id', 64)->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->json('changes')->nullable();                // {field: [old, new]} or action form data (redacted)
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
