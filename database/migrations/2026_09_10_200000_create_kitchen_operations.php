<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('warning_after_seconds')->default(600);
            $table->unsignedInteger('late_after_seconds')->default(1200);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'is_active', 'position']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
            $table->index(['restaurant_id', 'kitchen_station_id']);
        });
        Schema::create('kitchen_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_round_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('in_progress');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('table_name');
            $table->string('zone_name')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('room_acknowledged_at')->nullable();
            $table->foreignId('room_acknowledged_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'order_round_id']);
            $table->index(['restaurant_id', 'status', 'submitted_at']);
        });
        Schema::create('kitchen_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
            $table->string('station_name')->default('Sin asignar');
            $table->string('product_name');
            $table->string('format_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('cancelled_quantity')->default(0);
            $table->json('modifiers')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedBigInteger('version')->default(1);
            $table->dateTime('queued_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'kitchen_station_id', 'status', 'queued_at']);
            $table->unique(['kitchen_dispatch_id', 'order_line_id']);
        });
        Schema::create('kitchen_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->foreignId('acknowledged_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('raised_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status', 'raised_at']);
        });
        Schema::create('kitchen_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('type', 50);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['restaurant_id', 'id']);
            $table->index(['restaurant_id', 'kitchen_station_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_events');
        Schema::dropIfExists('kitchen_cancellations');
        Schema::dropIfExists('kitchen_items');
        Schema::dropIfExists('kitchen_dispatches');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('kitchen_station_id'));
        Schema::dropIfExists('kitchen_stations');
    }
};
