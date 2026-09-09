<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $request->route('restaurant');

        abort_unless($restaurant instanceof Restaurant, 404);

        // El propietario global entra en cualquier tenant por el flujo
        // autorizado (navegación/admin). Los datos siguen acotados al
        // restaurante del binding: el tenancy no desaparece.
        if ($request->user()->isPlatformOwner()) {
            return $next($request);
        }

        abort_unless($restaurant->is_active, 403);
        abort_unless($request->user()->restaurants()->whereKey($restaurant->getKey())->exists(), 403);

        return $next($request);
    }
}
