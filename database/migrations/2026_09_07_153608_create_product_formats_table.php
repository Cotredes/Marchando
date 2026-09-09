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
        Schema::create('product_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id');
            $table->string('name', 100);
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'id']);
            $table->foreign(['restaurant_id', 'product_id'])
                ->references(['restaurant_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->index(['restaurant_id', 'product_id', 'deleted_at', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_formats');
    }
};
