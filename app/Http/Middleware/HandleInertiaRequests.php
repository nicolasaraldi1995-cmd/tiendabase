<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
use App\Models\Pagina;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $cart = session('cart', []);
        $configuracion = Configuracion::actual();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Los precios son solo para clientes con cuenta: la interfaz
                // necesita saberlo para mostrar el aviso en vez del precio.
                'puedeVerPrecios' => $request->user() !== null,
                // Para esconder los accesos internos (lista de precios) al cliente.
                'esStaff' => (bool) ($request->user()?->isAdmin() || $request->user()?->isOperador()),
            ],
            // Liviano a propósito (nada de DB): el carrito completo con imagen/marca/
            // categoría se resuelve una sola vez, en CartController::index (/carrito).
            'cartCount' => array_sum($cart),
            'cartPresentacionIds' => array_map('intval', array_keys($cart)),
            'envioGratisDesde' => (float) $configuracion->envio_gratis_desde,
            'controlarStock' => (bool) $configuracion->controlar_stock,
            // Identidad del negocio, editable desde el panel (Configuración):
            // el layout público arma marca, footer y contacto con esto.
            'negocio' => [
                'nombre' => $configuracion->nombre_negocio,
                'eslogan' => $configuracion->eslogan,
                'descripcion' => $configuracion->descripcion,
                'direccion' => $configuracion->direccion,
                'ciudad' => $configuracion->ciudad,
                'telefono' => $configuracion->telefono,
                'whatsapp' => $configuracion->whatsapp,
                'instagram' => $configuracion->instagram,
                'logo' => $configuracion->logo_url,
                'mediosPago' => $configuracion->mediosPago(),
                'marcaDestacada' => $configuracion->marca_destacada_id
                    ? $configuracion->marcaDestacada()->first(['id', 'nombre'])
                    : null,
            ],
            // Páginas de contenido del negocio, para listarlas en el pie.
            'paginas' => Pagina::activos()->get(['titulo', 'slug']),
            // Interruptores por rubro (panel → Configuración): apagan secciones
            // enteras de la tienda para negocios que no las usan.
            'secciones' => [
                'filtrosAlimentos' => (bool) $configuracion->mostrar_filtros_alimentos,
                'listaPrecios' => (bool) $configuracion->mostrar_lista_precios,
                'combos' => (bool) $configuracion->mostrar_combos,
                'ofertas' => (bool) $configuracion->mostrar_ofertas,
            ],
        ];
    }
}
