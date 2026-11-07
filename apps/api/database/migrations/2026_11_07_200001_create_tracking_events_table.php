<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (event_id, destination): a server-side send log and the dedupe guard.
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->index();
            $table->string('event_id', 100);
            $table->string('order_id', 20)->nullable()->index();
            $table->string('destination', 20)->index(); // delivro | meta | ga4 | tiktok
            $table->string('status', 12)->default('queued')->index(); // queued | sent | failed | skipped
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'destination']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
