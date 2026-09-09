<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('source')->default('pin');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'employee_id', 'started_at']);
            $table->index(['restaurant_id', 'ended_at']);
            $table->unique(['restaurant_id', 'employee_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_intervals');
    }
};
