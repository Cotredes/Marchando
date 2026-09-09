<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_interval_corrections', function (Blueprint $table) {
            $table->dateTime('previous_started_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_interval_corrections', function (Blueprint $table) {
            $table->dateTime('previous_started_at')->nullable(false)->change();
        });
    }
};
