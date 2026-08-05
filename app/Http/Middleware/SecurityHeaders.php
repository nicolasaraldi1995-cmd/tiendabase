<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // ponytail: el panel /admin (Filament + Livewire, modales, notificaciones)
        // no se probó contra un CSP y queda afuera para no arriesgar romperlo.
        // 'unsafe-inline' en script/style es el techo del sitio público (JSON-LD
        // de productos, estilos inline de Vue) -- subirlo de nivel implica pasar
        // a nonces por request.
        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // La segunda condición deja pasar el CSP propio de una respuesta: el de
        // /media es mucho más cerrado (sandbox), y si este lo pisara, un SVG
        // subido al catálogo volvería a poder ejecutar scripts.
        if (! $request->is('admin*') && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode(' ', [
                "default-src 'self';",
                "script-src 'self' 'unsafe-inline';",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net;",
                "font-src 'self' https://fonts.bunny.net;",
                "img-src 'self' data:;",
                "connect-src 'self';",
                "frame-ancestors 'self';",
                "object-src 'none';",
                "base-uri 'self';",
                "form-action 'self';",
            ]));
        }

        return $response;
    }
}
