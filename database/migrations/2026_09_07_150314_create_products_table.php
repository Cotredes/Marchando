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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id');
            $table->string('name', 150);
            $table->string('short_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('available_dine_in')->default(true);
            $table->boolean('available_takeaway')->default(false);
            $table->boolean('available_delivery')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'id']);
            $table->foreign(['restaurant_id', 'category_id'])
                ->references(['restaurant_id', 'id'])
                ->on('categories')
                ->restrictOnDelete();
            $table->index(['restaurant_id', 'category_id', 'deleted_at', 'position', 'id']);
            $table->index(['restaurant_id', 'deleted_at', 'is_active', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
