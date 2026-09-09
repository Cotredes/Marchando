<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_format_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('format_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_base_minor');
            $table->unsignedBigInteger('unit_modifiers_minor')->default(0);
            $table->unsignedBigInteger('unit_total_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->json('snapshot');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['restaurant_id', 'order_id']);
        });

        Schema::create('order_line_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_modifier_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('modifier_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('option_name');
            $table->string('instruction', 30)->default('normal');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_delta_minor');
            $table->bigInteger('total_delta_minor');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_modifiers');
        Schema::dropIfExists('order_lines');
    }
};
