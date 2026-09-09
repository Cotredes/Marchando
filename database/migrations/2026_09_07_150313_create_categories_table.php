<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('available_dine_in')->default(true);
            $table->boolean('available_takeaway')->default(false);
            $table->boolean('available_delivery')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'id']);
            $table->index(['restaurant_id', 'deleted_at', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
