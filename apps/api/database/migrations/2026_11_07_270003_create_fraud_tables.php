<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fraud check (App\Services\Fraud) and the phone/IP/device blocklist.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('fraud_score')->nullable();
            $table->string('fraud_level', 10)->nullable()->index(); // low | medium | high
            $table->json('fraud_reasons')->nullable();
            $table->boolean('fraud_review')->default(false)->index(); // "Needs review": no automatic verification
            $table->timestamp('fraud_checked_at')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('device_id', 64)->nullable();
        });

        // Courier delivery history per phone, one row per courier (cached 7 days).
        Schema::create('courier_checks', function (Blueprint $table) {
            $table->id();
            $table->string('phone_hash', 64)->index();
            $table->string('courier', 20);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('cancelled')->default(0);
            $table->text('raw')->nullable(); // encrypted JSON
            $table->string('error')->nullable();
            $table->timestamp('checked_at');
            $table->unique(['phone_hash', 'courier']);
        });

        Schema::create('blocklist', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                 // phone | ip | device
            $table->string('value_hash', 64);           // HMAC(app key, normalised value) for lookups
            $table->text('value');                      // encrypted original (shown to staff)
            $table->string('reason')->nullable();
            $table->string('source', 20)->default('staff'); // staff | honeypot | bot | fraud
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['type', 'value_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocklist');
        Schema::dropIfExists('courier_checks');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['fraud_score', 'fraud_level', 'fraud_reasons', 'fraud_review', 'fraud_checked_at', 'client_ip', 'device_id']));
    }
};
