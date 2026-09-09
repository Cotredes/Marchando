<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Autoridad global centralizada: el propietario de la plataforma
        // supera cualquier ability de restaurante sin necesidad de membership.
        // El tenancy sigue intacto porque las rutas y consultas continúan
        // acotadas al restaurante resuelto por binding en cada request.
        Gate::before(fn (User $user): ?bool => $user->isPlatformOwner() ? true : null);
    }
}
