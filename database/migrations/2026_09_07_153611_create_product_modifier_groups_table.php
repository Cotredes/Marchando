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
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id');
            $table->foreignId('modifier_group_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['restaurant_id', 'product_id', 'modifier_group_id']);
            $table->unique(['restaurant_id', 'id']);
            $table->foreign(['restaurant_id', 'product_id'])
                ->references(['restaurant_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['restaurant_id', 'modifier_group_id'])
                ->references(['restaurant_id', 'id'])
                ->on('modifier_groups')
                ->restrictOnDelete();
            $table->index(['restaurant_id', 'product_id', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
    }
};
