<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Messaging module: templates, delivery log, push devices, in-app notifications, synced carts, user prefs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->default('en');
            }
            if (! Schema::hasColumn('users', 'notify_prefs')) {
                $table->json('notify_prefs')->nullable();
            }
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60);
            $table->string('channel', 10); // sms | email | push
            $table->string('locale', 5)->default('en'); // en | bn
            $table->string('subject')->nullable(); // email subject / push title
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['key', 'channel', 'locale']);
        });

        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_id', 40)->nullable()->index();
            $table->string('event', 60)->index();
            $table->string('category', 20)->default('transactional'); // transactional | marketing | otp | admin
            $table->string('channel', 10)->index(); // sms | email | push
            $table->string('locale', 5)->default('en');
            $table->string('to', 190)->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('meta')->nullable(); // email blocks, push data
            $table->string('status', 12)->default('queued')->index(); // queued | sent | failed
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('segments')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('broadcast_id', 40)->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['event', 'order_id']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // expo | web
            $table->string('token', 500); // Expo push token or Web Push endpoint
            $table->string('token_hash', 64)->unique();
            $table->json('keys')->nullable(); // web push { p256dh, auth }
            $table->string('platform', 20)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('icon', 30)->nullable();
            $table->string('link')->nullable();
            $table->string('image', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('items');
            $table->unsignedInteger('total_bdt')->default(0);
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->timestamp('reminded_1h_at')->nullable();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('message_templates');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['locale', 'notify_prefs']));
    }
};
