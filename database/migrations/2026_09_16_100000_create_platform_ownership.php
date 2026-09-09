<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Representación técnica de la propiedad global de la plataforma.
     *
     * - users.is_platform_owner: única fuente de autoridad global. Ningún
     *   controlador, vista o policy compara emails; solo User::isPlatformOwner().
     * - users.is_active / restaurants.is_active: desactivación sin borrado,
     *   el histórico nunca se elimina.
     * - platform_audits: registro append-only de acciones del propietario.
     *
     * El email del propietario inicial solo se usa aquí (bootstrap) y en
     * DatabaseSeeder. A partir de ese momento la propiedad vive en el flag
     * booleano, no en el texto del email.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_owner')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_platform_owner');
        });

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('slug');
        });

        Schema::create('platform_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120);
            $table->text('detail')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'created_at']);
        });

        DB::table('users')
            ->where('email', env('PLATFORM_OWNER_EMAIL', 'angel@munasa.es'))
            ->update(['is_platform_owner' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audits');

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_platform_owner', 'is_active']);
        });
    }
};
