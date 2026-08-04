<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que editan la tienda desde la propia tienda (el editor del menú):
 * son del dueño, ni siquiera del operador.
 */
class SoloAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
