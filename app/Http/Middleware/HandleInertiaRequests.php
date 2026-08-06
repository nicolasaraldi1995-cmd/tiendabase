<?php

namespace App\Http\Middleware;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Marca;
use App\Models\Pagina;
use App\Models\SeccionMenu;
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
            // Ya resuelto para este cliente (0 = no le corre): así la tienda no
            // repite la regla de a quién se le exige mínimo.
            'pedidoMinimo' => $configuracion->pedidoMinimoPara($request->user()),
            'controlarStock' => (bool) $configuracion->controlar_stock,
            'haceEnvios' => (bool) $configuracion->hace_envios,
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
            // El menú de la tienda, armado por el negocio desde el panel. Se
            // saltean los ítems que quedaron apuntando a algo ya borrado.
            'menu' => SeccionMenu::activos()->get()
                ->map(fn (SeccionMenu $s) => [
                    'id' => $s->id,
                    'titulo' => $s->titulo,
                    'emoji' => $s->emoji,
                    'url' => $s->url,
                ])
                ->filter(fn (array $item) => $item['url'] !== null)
                ->values(),
            // Lo que necesita el editor del menú sobre la tienda. Solo viaja
            // para el dueño: a un cliente no le sirve y no tiene por qué verlo.
            'menuEditor' => $request->user()?->isAdmin()
                ? [
                    'destinos' => SeccionMenu::DESTINOS,
                    'destinosConValor' => SeccionMenu::DESTINOS_CON_VALOR,
                    'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
                    'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
                    'paginas' => Pagina::orderBy('titulo')->get(['slug', 'titulo']),
                    // Incluye los apagados: el dueño tiene que poder volver a prenderlos.
                    'items' => SeccionMenu::orderBy('orden')->orderBy('id')->get(['id', 'titulo', 'emoji', 'activo']),
                ]
                : null,
            // Interruptores por rubro (panel → Configuración): apagan secciones
            // enteras de la tienda para negocios que no las usan.
            // Las etiquetas que el negocio quiso ver como filtro en el menú.
            // Antes eran tres links fijos (Sin TACC / Fríos / Congelados)
            // escritos en el marco: solo le servían a un rubro y ni siquiera
            // existían en las otras plantillas.
            'filtros' => Etiqueta::enFiltros()->get()->map(fn (Etiqueta $e) => $e->paraLaTienda())->values(),
            'secciones' => [
                'listaPrecios' => (bool) $configuracion->mostrar_lista_precios,
                // Solo la franja de la portada: el ítem del menú es una fila
                // de secciones_menu, con su propio encendido.
                'combos' => (bool) $configuracion->mostrar_combos,
            ],
        ];
    }
}
