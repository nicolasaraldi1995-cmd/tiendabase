<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Vite::prefetch(concurrency: 3);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // En desarrollo solo el largo, para no pelear con las claves de prueba.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        $this->limitesPorSeparado();
    }

    /**
     * Un balde por cosa, en vez de uno solo compartido.
     *
     * Laravel arma la clave del tope con el id del usuario pero sin la ruta:
     * con "throttle:30,1" en el carrito y "throttle:10,1" en el checkout, los
     * dos contaban en el mismo balde y el cliente que tocaba muchas veces el
     * +/- se quedaba sin poder confirmar el pedido.
     */
    private function limitesPorSeparado(): void
    {
        $porUsuarioOIp = fn (Request $peticion) => $peticion->user()?->id ?: $peticion->ip();

        foreach ([
            'carrito' => 60,
            'pedido' => 60,
            'checkout' => 10,
            'buscar' => 60,
            'entrar' => 20,
            // Los avisos de MercadoPago vienen de sus servidores, no de una
            // persona: el tope es holgado porque un pico legítimo (varios
            // pedidos pagándose a la vez, más reintentos) no puede quedar
            // afuera. Lo que frena de verdad la puerta es la firma.
            'webhook' => 120,
        ] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $peticion) => Limit::perMinute($porMinuto)->by($nombre.'|'.$porUsuarioOIp($peticion)));
        }

        // Alta y recuperación por hora: acá lo que se frena es un barrido
        // automático, no a alguien apurado.
        foreach (['registrarse' => 5, 'recuperar' => 5, 'restablecer' => 10] as $nombre => $porHora) {
            RateLimiter::for($nombre, fn (Request $peticion) => Limit::perHour($porHora)->by($nombre.'|'.$porUsuarioOIp($peticion)));
        }
    }
}
