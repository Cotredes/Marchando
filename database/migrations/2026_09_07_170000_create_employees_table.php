<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('display_name', 80);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('pin_hash')->nullable();
            $table->string('pin_fingerprint', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['restaurant_id', 'user_id']);
            $table->unique(['restaurant_id', 'pin_fingerprint']);
            $table->index(['restaurant_id', 'is_active', 'display_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
