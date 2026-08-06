<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SoloAdmin;
use App\Http\Middleware\SoloStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            // Ata la sesión a la contraseña: quien se quedó con la cookie deja
            // de entrar en cuanto la víctima cambia la clave. El panel ya lo
            // tenía; el sitio público no.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => SoloStaff::class,
            'admin' => SoloAdmin::class,
        ]);

        // El aviso de pago lo manda un servidor de MercadoPago, no un
        // navegador: no tiene sesión con la que obtener un token de formulario.
        // Exigirlo daría 419 y no llegaría a acreditarse ningún pago. Lo que
        // autentica esta ruta es la firma del aviso, verificada contra el
        // secreto del negocio (ver MercadoPagoWebhookController).
        $middleware->validateCsrfTokens(except: [
            'webhooks/mercadopago',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
