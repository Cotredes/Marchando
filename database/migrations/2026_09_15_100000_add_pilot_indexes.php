<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status', 'paid_at'], 'orders_restaurant_status_paid_idx');
            $table->index(['restaurant_id', 'customer_id'], 'orders_restaurant_customer_idx');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status', 'succeeded_at'], 'payments_restaurant_status_succ_idx');
        });
        Schema::table('kitchen_items', function (Blueprint $table) {
            $table->index(['restaurant_id', 'status', 'queued_at'], 'kitchen_items_restaurant_status_queued_idx');
        });
        Schema::table('order_events', function (Blueprint $table) {
            $table->index(['restaurant_id', 'created_at'], 'order_events_restaurant_created_idx');
        });
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->index(['restaurant_id', 'printer_id', 'status'], 'print_jobs_restaurant_printer_status_idx');
        });
        Schema::table('online_payment_intents', function (Blueprint $table) {
            $table->index(['restaurant_id', 'public_order_request_id'], 'online_intents_restaurant_request_idx');
        });
    }

    public function down(): void
    {
        Schema::table('online_payment_intents', function (Blueprint $table) {
            $table->dropIndex('online_intents_restaurant_request_idx');
        });
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex('print_jobs_restaurant_printer_status_idx');
        });
        Schema::table('order_events', function (Blueprint $table) {
            $table->dropIndex('order_events_restaurant_created_idx');
        });
        Schema::table('kitchen_items', function (Blueprint $table) {
            $table->dropIndex('kitchen_items_restaurant_status_queued_idx');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_restaurant_status_succ_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_restaurant_customer_idx');
            $table->dropIndex('orders_restaurant_status_paid_idx');
        });
    }
};
