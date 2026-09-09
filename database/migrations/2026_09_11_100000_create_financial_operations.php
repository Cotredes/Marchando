<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('closed_at');
        });

        Schema::table('order_split_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('source_order_version')->nullable()->after('source_total_minor');
        });

        Schema::table('order_split_parts', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(1)->after('status');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 80);
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->unsignedTinyInteger('open_slot')->nullable();
            $table->unsignedBigInteger('opening_float_minor')->default(0);
            $table->unsignedBigInteger('expected_cash_minor')->nullable();
            $table->unsignedBigInteger('declared_cash_minor')->nullable();
            $table->bigInteger('difference_minor')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'cash_register_id', 'open_slot']);
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_split_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_split_part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('succeeded');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('request_key', 120);
            $table->string('reference', 120)->nullable();
            $table->date('business_date');
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
            $table->index(['restaurant_id', 'order_id', 'status']);
            $table->index(['order_split_part_id', 'status']);
        });

        Schema::create('payment_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('tendered_minor')->nullable();
            $table->unsignedBigInteger('change_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500);
            $table->string('request_key', 120);
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 500)->nullable();
            $table->string('request_key', 120)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
            $table->index(['cash_session_id', 'type']);
        });

        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('declared_cash_minor');
            $table->unsignedBigInteger('expected_cash_minor');
            $table->bigInteger('difference_minor');
            $table->json('denominations')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_tenders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('payment_methods');
        Schema::table('order_split_parts', fn (Blueprint $table) => $table->dropColumn('version'));
        Schema::table('order_split_plans', fn (Blueprint $table) => $table->dropColumn('source_order_version'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['payment_status', 'paid_at']));
    }
};
