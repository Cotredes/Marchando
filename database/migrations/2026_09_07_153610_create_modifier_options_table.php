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
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id');
            $table->string('name', 150);
            $table->bigInteger('price_delta_minor')->default(0);
            $table->unsignedSmallInteger('max_quantity')->default(1);
            $table->string('instruction', 20)->default('normal');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'id']);
            $table->foreign(['restaurant_id', 'modifier_group_id'])
                ->references(['restaurant_id', 'id'])
                ->on('modifier_groups')
                ->cascadeOnDelete();
            $table->index(['restaurant_id', 'modifier_group_id', 'deleted_at', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};
