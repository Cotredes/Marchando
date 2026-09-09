<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('current_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('channel', 30)->default('dine_in');
            $table->string('status', 30)->default('open');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->date('business_date');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status', 'opened_at']);
            $table->unique(['restaurant_id', 'id']);
        });

        Schema::create('active_table_orders', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['restaurant_id', 'dining_table_id']);
            $table->unique(['restaurant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_table_orders');
        Schema::dropIfExists('orders');
    }
};
