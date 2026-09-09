<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('uses')->nullable();
            $table->unsignedTinyInteger('paper_width')->default(80);
            $table->string('connection', 20)->default('network');
            $table->string('endpoint', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('open_drawer')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status_note', 200)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('kitchen_station_printer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['kitchen_station_id', 'printer_id']);
        });

        Schema::create('print_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('api_token_hash', 64)->unique();
            $table->string('pairing_code_hash', 64)->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('agent_version', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('print_connector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('reference', 120);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->boolean('is_reprint')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'reference']);
            $table->index(['restaurant_id', 'status', 'created_at']);
        });

        Schema::create('cash_drawer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger', 20)->default('manual');
            $table->string('status', 20)->default('requested');
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'created_at']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('status', 20)->default('not_configured');
            $table->string('mode', 10)->nullable();
            $table->json('settings')->nullable();
            $table->text('secrets')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'provider']);
        });

        Schema::create('online_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_order_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30);
            $table->string('provider', 30)->default('stripe');
            $table->string('mode', 10)->default('test');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('EUR');
            $table->string('provider_intent_id', 120)->nullable()->unique();
            $table->text('client_secret')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('failure_reason', 500)->nullable();
            $table->string('request_key', 120);
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('online_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_payment_intent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('requested');
            $table->string('provider_refund_id', 120)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->string('request_key', 120);
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
        });

        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('provider_event_id', 160)->unique();
            $table->string('type', 120);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('method', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'channel', 'method']);
        });

        Schema::create('fiscal_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('nif', 20);
            $table->string('legal_name', 200);
            $table->boolean('is_default')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'nif']);
        });

        Schema::create('fiscal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_identity_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_document_id')->constrained()->restrictOnDelete();
            $table->string('environment', 10)->default('test');
            $table->string('record_type', 20)->default('alta');
            $table->string('fiscal_type', 5)->default('F1');
            $table->string('serie', 20);
            $table->string('numero', 60);
            $table->date('issue_date');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('tax_total_minor');
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->text('qr_content');
            $table->string('software_version', 40);
            $table->string('status', 30)->default('pending');
            $table->json('aeat_response')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['sale_document_id', 'record_type']);
            $table->index(['restaurant_id', 'fiscal_identity_id', 'environment', 'status']);
        });

        Schema::create('fiscal_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_record_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('response_code', 40)->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('sales_account', 20)->nullable();
            $table->string('vat_account', 20)->nullable();
            $table->string('customers_account', 20)->nullable();
            $table->string('journal', 20)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id']);
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->timestamps();
        });

        Schema::create('outbound_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('signing_secret');
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('outbound_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('response_code')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->index(['outbound_webhook_endpoint_id', 'status']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120)->nullable();
            $table->string('kind', 20);
            $table->unsignedBigInteger('value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('channels')->nullable();
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->unsignedBigInteger('max_uses')->nullable();
            $table->unsignedBigInteger('uses_count')->default(0);
            $table->boolean('one_per_customer')->default(false);
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_fp', 64)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['order_id']);
            $table->index(['coupon_id', 'customer_fp']);
        });

        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('goal')->default(10);
            $table->foreignId('reward_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loyalty_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stamps')->default(0);
            $table->unsignedInteger('rewards_available')->default(0);
            $table->timestamps();
            $table->unique(['loyalty_program_id', 'customer_id']);
        });

        Schema::create('loyalty_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->integer('quantity');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['loyalty_program_id', 'customer_id']);
        });

        Schema::table('order_discounts', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->string('source', 20)->default('manual')->after('kind');
        });

        Schema::table('public_order_requests', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('accepted_round_id')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('public_order_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });
        Schema::table('order_discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('loyalty_events');
        Schema::dropIfExists('loyalty_progress');
        Schema::dropIfExists('loyalty_programs');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('outbound_webhook_deliveries');
        Schema::dropIfExists('outbound_webhook_endpoints');
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('fiscal_attempts');
        Schema::dropIfExists('fiscal_records');
        Schema::dropIfExists('fiscal_identities');
        Schema::dropIfExists('channel_payment_methods');
        Schema::dropIfExists('provider_webhook_events');
        Schema::dropIfExists('online_refunds');
        Schema::dropIfExists('online_payment_intents');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('cash_drawer_events');
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_connectors');
        Schema::dropIfExists('kitchen_station_printer');
        Schema::dropIfExists('printers');
    }
};
