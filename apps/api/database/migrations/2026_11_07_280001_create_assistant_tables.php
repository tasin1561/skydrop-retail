<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('client', 12)->default('web'); // web | mobile
            $table->string('locale', 4)->nullable(); // en | bn
            $table->string('title', 120)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('role', 12); // user | assistant
            $table->text('content'); // PII-scrubbed
            $table->json('cards')->nullable(); // structured events shown to the shopper
            $table->json('tools')->nullable(); // [{ name, input, ok }]
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->boolean('degraded')->default(false);
            $table->boolean('stopped')->default(false);
            $table->string('error', 120)->nullable();
            $table->timestamps();
            $table->index(['role', 'created_at']);
        });

        Schema::create('assistant_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->index();
            $table->uuid('conversation_id')->index();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->smallInteger('rating'); // 1 = thumbs up, -1 = thumbs down
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_feedback');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
