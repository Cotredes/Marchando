<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('employee_operational_role', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_role_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_id', 'operational_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_operational_role');
        Schema::dropIfExists('operational_roles');
    }
};
