<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allows_manual_price')->default(false)->after('is_available');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('voided_quantity')->default(0)->after('quantity');
            $table->unsignedBigInteger('manual_unit_total_minor')->nullable()->after('unit_total_minor');
            $table->unsignedBigInteger('active_line_total_minor')->default(0)->after('line_total_minor');
        });
        DB::statement('UPDATE order_lines SET active_line_total_minor = line_total_minor WHERE active_line_total_minor = 0');

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_minor')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('reason', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['restaurant_id', 'order_id', 'is_active']);
        });

        Schema::create('order_split_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20);
            $table->string('status', 20)->default('confirmed');
            $table->unsignedBigInteger('source_total_minor');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('request_key')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'request_key']);
            $table->index(['restaurant_id', 'order_id', 'status']);
        });
        Schema::create('order_split_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_split_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label', 80);
            $table->unsignedBigInteger('total_minor');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['order_split_plan_id', 'sequence']);
        });
        Schema::create('order_split_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_split_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
            $table->unique(['order_split_part_id', 'order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_split_allocations');
        Schema::dropIfExists('order_split_parts');
        Schema::dropIfExists('order_split_plans');
        Schema::dropIfExists('order_discounts');
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn(['voided_quantity', 'manual_unit_total_minor', 'active_line_total_minor']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allows_manual_price');
        });
    }
};
