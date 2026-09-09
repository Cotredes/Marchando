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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('establishment_type')->nullable();
            $table->text('description')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('website')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('España');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->decimal('default_vat', 5, 2)->nullable();
            $table->text('ticket_footer')->nullable();
            $table->string('timezone')->default('Europe/Madrid');
            $table->boolean('dine_in_enabled')->default(true);
            $table->boolean('takeaway_enabled')->default(false);
            $table->unsignedSmallInteger('takeaway_prep_minutes')->nullable();
            $table->boolean('takeaway_use_general_schedule')->default(true);
            $table->boolean('delivery_enabled')->default(false);
            $table->decimal('delivery_radius_km', 6, 2)->nullable();
            $table->decimal('delivery_fee', 8, 2)->nullable();
            $table->decimal('delivery_minimum_order', 8, 2)->nullable();
            $table->unsignedSmallInteger('delivery_prep_minutes')->nullable();
            $table->boolean('delivery_use_general_schedule')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            //
        });
    }
};
