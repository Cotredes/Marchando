<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedTinyInteger('draft_slot')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('submission_key')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'order_id', 'sequence']);
            $table->unique(['restaurant_id', 'order_id', 'draft_slot']);
            $table->unique(['restaurant_id', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_rounds');
    }
};
