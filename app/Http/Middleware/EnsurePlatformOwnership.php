<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOwnership
{
    /**
     * Solo el propietario global de Marchando puede entrar en /admin.
     * La comprobación vive en User::isPlatformOwner(); prohibido
     * comparar emails en controladores o vistas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformOwner() === true, 403);

        return $next($request);
    }
}
