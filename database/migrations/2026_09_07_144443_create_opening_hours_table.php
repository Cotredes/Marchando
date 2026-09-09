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
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('context', 20);
            $table->unsignedTinyInteger('iso_weekday');
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->unique(['restaurant_id', 'context', 'iso_weekday', 'start_minute', 'end_minute']);
            $table->index(['restaurant_id', 'context', 'iso_weekday']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
