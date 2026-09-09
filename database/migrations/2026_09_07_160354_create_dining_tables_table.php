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
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id');
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->char('qr_token', 64)->unique();
            $table->boolean('qr_is_active')->default(false);
            $table->timestamp('qr_activated_at')->nullable();
            $table->timestamp('qr_revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'id']);
            $table->unique(['zone_id', 'name']);
            $table->foreign(['restaurant_id', 'zone_id'])
                ->references(['restaurant_id', 'id'])
                ->on('zones')
                ->restrictOnDelete();
            $table->index(['restaurant_id', 'zone_id', 'deleted_at', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
