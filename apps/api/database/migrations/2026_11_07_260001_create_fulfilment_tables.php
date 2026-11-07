<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfilment operations: Kolkata hub receiving + consolidation, paid unboxing, returns, live tracking, supplier price alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->string('code', 30)->unique(); // printed as Code 128
            $table->decimal('weight_kg', 8, 3)->default(0);
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->foreignId('staff_id')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hub_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->index();
            $table->string('order_id', 12)->index();
            $table->foreignId('staff_id')->nullable();
            $table->decimal('weight_kg', 8, 3);
            $table->decimal('length_cm', 7, 1)->nullable();
            $table->decimal('width_cm', 7, 1)->nullable();
            $table->decimal('height_cm', 7, 1)->nullable();
            $table->string('condition', 20)->default('ok'); // ok | damaged | wrong_item
            $table->text('notes')->nullable();
            $table->json('photos')->nullable(); // public disk paths
            $table->string('video')->nullable(); // public disk path (unboxing)
            $table->foreignId('box_id')->nullable()->index();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('hub_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // return_to_seller
            $table->string('status', 20)->default('open'); // open | done
            $table->string('order_id', 12)->index();
            $table->foreignId('order_item_id')->nullable();
            $table->foreignId('hub_receipt_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->foreignId('user_id')->index();
            $table->string('stage', 20)->default('delivered'); // india (unboxing / hub) | delivered
            $table->json('items'); // [{orderItemId, qty}]
            $table->string('reason', 30); // damaged | wrong_item | not_as_described | size_issue | changed_mind
            $table->text('note')->nullable();
            $table->json('photos')->nullable(); // upload ids
            $table->string('resolution', 20); // refund | replacement | store_credit
            $table->string('status', 30)->default('requested');
            $table->text('staff_notes')->nullable();
            $table->decimal('refund_bdt', 12, 2)->default(0);
            $table->string('replacement_order_id', 12)->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('timeline')->nullable(); // [{status, at, note, by}]
            $table->timestamps();
        });

        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->string('purpose', 30)->default('return');
            $table->timestamps();
        });

        Schema::create('skydrop_events', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->string('event', 60);
            $table->string('status', 20)->nullable(); // mapped order status, when any
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('note')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('at')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('unboxing')->default(false);
            $table->decimal('unboxing_fee_bdt', 10, 2)->default(0);
            $table->string('unboxing_status', 30)->nullable(); // awaiting_customer_approval | approved | auto_approved | problem
            $table->timestamp('unboxing_requested_at')->nullable();
            $table->timestamp('unboxing_decided_at')->nullable();
            $table->decimal('actual_weight_kg', 8, 3)->nullable(); // measured at the Kolkata hub (profit report)
            $table->json('weight_flag')->nullable(); // {estimateKg, actualKg, variancePct, extraBdt, resolution}
            $table->boolean('price_alert')->default(false);
            $table->json('pod')->nullable(); // proof of delivery {photoUrl, signatureUrl, receivedBy, at}
            $table->json('rider_location')->nullable(); // {lat, lng, at}
            $table->string('replacement_of', 12)->nullable()->index();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('hub_received_at')->nullable();
            $table->string('hub_condition', 20)->nullable();
            $table->json('price_alert')->nullable(); // {oldInr, newInr, risePct, inStock, extraBdt, at, resolution}
            $table->timestamp('price_checked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['hub_received_at', 'hub_condition', 'price_alert', 'price_checked_at', 'cancelled_at']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['unboxing', 'unboxing_fee_bdt', 'unboxing_status', 'unboxing_requested_at',
            'unboxing_decided_at', 'actual_weight_kg', 'weight_flag', 'price_alert', 'pod', 'rider_location', 'replacement_of']));
        foreach (['skydrop_events', 'uploads', 'return_requests', 'hub_tasks', 'hub_receipts', 'hub_boxes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
