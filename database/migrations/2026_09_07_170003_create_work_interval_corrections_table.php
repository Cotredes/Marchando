<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_interval_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_interval_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->dateTime('previous_started_at');
            $table->dateTime('previous_ended_at')->nullable();
            $table->dateTime('corrected_started_at');
            $table->dateTime('corrected_ended_at')->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['restaurant_id', 'work_interval_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_interval_corrections');
    }
};
