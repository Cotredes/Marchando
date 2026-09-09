<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('public_menu_enabled')->default(true)->after('currency');
            $table->boolean('qr_ordering_enabled')->default(true)->after('public_menu_enabled');
            $table->string('qr_acceptance_mode', 20)->default('manual')->after('qr_ordering_enabled');
            $table->string('takeaway_acceptance_mode', 20)->default('manual')->after('qr_acceptance_mode');
            $table->string('delivery_acceptance_mode', 20)->default('manual')->after('takeaway_acceptance_mode');
            $table->boolean('takeaway_scheduling_enabled')->default(true)->after('delivery_acceptance_mode');
            $table->boolean('delivery_scheduling_enabled')->default(true)->after('takeaway_scheduling_enabled');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['dining_table_id']);
            $table->foreignId('dining_table_id')->nullable()->change();
            $table->string('origin', 30)->default('pos')->after('channel');
        });

        Schema::table('kitchen_dispatches', function (Blueprint $table) {
            $table->dropColumn(['table_name', 'zone_name']);
            $table->string('fulfillment_label')->nullable()->after('status');
            $table->string('channel', 30)->default('dine_in')->after('fulfillment_label');
        });

        Schema::create('public_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accepted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('accepted_round_id')->nullable()->constrained('order_rounds')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30)->default('pending');
            $table->string('public_token_hash', 64);
            $table->string('request_key', 120);
            $table->string('payload_hash', 64);
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email', 160)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->string('fulfillment_mode', 20)->default('asap');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
            $table->unique('public_token_hash');
            $table->index(['restaurant_id', 'status', 'channel']);
        });

        Schema::create('public_order_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_order_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_format_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('format_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_total_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->json('selections')->nullable();
            $table->json('snapshot');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30)->default('accepted');
            $table->string('fulfillment_mode', 20)->default('asap');
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email', 160)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('delivery_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['restaurant_id', 'channel', 'status']);
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('label', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamps();
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('order_fulfillments');
        Schema::dropIfExists('public_order_request_lines');
        Schema::dropIfExists('public_order_requests');
        Schema::table('kitchen_dispatches', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_label', 'channel']);
            $table->string('table_name');
            $table->string('zone_name')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['dining_table_id']);
            $table->foreignId('dining_table_id')->nullable(false)->constrained()->restrictOnDelete()->change();
            $table->dropColumn('origin');
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['public_menu_enabled', 'qr_ordering_enabled', 'qr_acceptance_mode', 'takeaway_acceptance_mode', 'delivery_acceptance_mode', 'takeaway_scheduling_enabled', 'delivery_scheduling_enabled']);
        });
    }
};
