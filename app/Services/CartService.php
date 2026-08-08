<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * Resolves a session cart (presentacion_id => cantidad) into display-ready line items.
     */
    public function resolveItems(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        // El carrito arma los precios a mano (no pasa por Presentacion::toArray),
        // así que necesita su propio corte.
        $mostrarPrecios = auth()->check();

        // whereHas('producto') descarta presentaciones huérfanas (su producto fue
        // borrado): mejor que desaparezcan silenciosamente del carrito a que rompan
        // la página, ya que este resolver corre en cada request (ver HandleInertiaRequests).
        $presentaciones = Presentacion::with(['producto.marca', 'producto.categoria', 'producto.etiquetas' => fn ($e) => $e->activas()])
            ->whereIn('id', array_keys($cart))
            ->whereHas('producto')
            // Igual criterio que con las huérfanas: una presentación dada de baja
            // desaparece del carrito en vez de dejar que se pida algo que ya no
            // se vende.
            ->activos()
            ->get();

        $usuario = auth()->user();

        return $presentaciones->map(function (Presentacion $p) use ($cart, $mostrarPrecios, $usuario) {
            $cantidad = $cart[(string) $p->id];
            // El precio depende de quién compra y de cuánto lleva: un negocio
            // paga por mayor siempre, y cualquiera al llegar a la cantidad.
            $precio = $p->precioPara($usuario, $cantidad);

            $imagen = $p->imagen_url ?? $p->producto->imagen_url;

            return [
                'presentacion_id' => $p->id,
                'producto_id' => $p->producto_id,
                'nombre' => $p->producto->nombre,
                'marca' => $p->producto->marca->nombre ?? 'Sin marca',
                'categoria' => $p->producto->categoria->nombre ?? 'Sin categoría',
                'imagen' => $imagen,
                'unidad' => $p->unidad,
                'precio' => $mostrarPrecios ? $precio : null,
                'precio_original' => $mostrarPrecios ? (float) $p->precio : null,
                'en_oferta' => $p->estaEnOferta() && $precio === $p->precio_final,
                // "Llevando N te sale $X": solo si todavía no lo está pagando.
                'mayorista_desde' => $mostrarPrecios && $cantidad < ($p->cantidad_mayorista ?: PHP_INT_MAX)
                    ? $p->mejorPrecioPorCantidad($usuario)
                    : null,
                'cantidad' => $cantidad,
                'subtotal' => $mostrarPrecios ? round($precio * $cantidad, 2) : null,
                // Acá NO va el stock. El modelo lo borra a propósito para todo
                // el que no sea del negocio (ver Presentacion::attributesToArray),
                // pero esta lista se arma a mano y lo volvía a publicar: el
                // carrito le mostraba el inventario exacto a cualquiera, incluso
                // sin cuenta. No lo usa ninguna pantalla; el tope real de compra
                // lo pone el servidor al crear el pedido (PedidoItemObserver).
                // Las etiquetas del producto, para el cartelito y para saber
                // a qué ítems apunta cada aviso.
                'etiquetas' => $p->producto->etiquetas
                    ->where('activo', true)
                    ->map(fn ($e) => $e->paraLaTienda())
                    ->values()
                    ->all(),
            ];
        })->values()->toArray();
    }

    /**
     * Los avisos que le corresponden a este carrito: cada etiqueta con texto
     * que lleve alguno de los productos. Antes esto era un caso fijo de
     * "fríos o congelados" escrito en el controlador y en la vista.
     *
     * Se agrupan por texto: dos etiquetas con el mismo aviso (por ejemplo
     * "Frío" y "Congelado", que salen de la misma condición) muestran un solo
     * cartel en vez de dos idénticos.
     *
     * @param  array<int, array<string, mixed>>  $items  lo que devolvió resolveItems
     * @return array<int, array<string, mixed>>
     */
    public function avisosPara(array $items, ?User $usuario): array
    {
        // Hay clientes que ya conocen estas condiciones y no necesitan que se
        // las repitan en cada compra.
        if ($usuario?->omite_avisos) {
            return [];
        }

        $conAviso = Etiqueta::conAviso()->get()->keyBy('id');

        if ($conAviso->isEmpty()) {
            return [];
        }

        $porTexto = [];

        foreach ($items as $item) {
            foreach ($item['etiquetas'] ?? [] as $etiqueta) {
                $aviso = $conAviso->get($etiqueta['id']);

                if (! $aviso) {
                    continue;
                }

                $porTexto[$aviso->aviso] ??= ['texto' => $aviso->aviso, 'etiquetas' => [], 'presentaciones' => []];
                // Por id: el botón de "quitar del carrito" lo manda al servidor.
                $porTexto[$aviso->aviso]['etiquetas'][$aviso->id] = ['id' => $aviso->id, 'nombre' => $aviso->nombre];
                $porTexto[$aviso->aviso]['presentaciones'][] = $item['presentacion_id'];
            }
        }

        return collect($porTexto)
            ->map(fn (array $a) => [
                'texto' => $a['texto'],
                'etiquetas' => array_values($a['etiquetas']),
                'presentaciones' => array_values(array_unique($a['presentaciones'])),
            ])
            ->values()
            ->all();
    }

    public function total(array $cart): float
    {
        return collect($this->resolveItems($cart))->sum('subtotal');
    }

    /**
     * Products to suggest alongside the cart/checkout: things the customer bought
     * before that aren't in the current cart, filled out with same-category picks
     * if there isn't enough purchase history to reach 8.
     */
    public function recomendadosPara(?User $user, array $cartPresentacionIds): Collection
    {
        $cartProductoIds = Presentacion::whereIn('id', $cartPresentacionIds)->pluck('producto_id')->unique();

        $recomendados = collect();

        if ($user) {
            $historialProductoIds = PedidoItem::whereHas('pedido', fn ($q) => $q->where('user_id', $user->id)->where('estado', '!=', 'canceled'))
                ->join('presentaciones', 'pedido_items.presentacion_id', '=', 'presentaciones.id')
                ->whereNotIn('presentaciones.producto_id', $cartProductoIds)
                ->select('presentaciones.producto_id')
                ->distinct()
                ->pluck('producto_id');

            if ($historialProductoIds->isNotEmpty()) {
                $recomendados = Producto::activos()
                    ->whereIn('id', $historialProductoIds)
                    ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                    ->inRandomOrder()
                    ->take(8)
                    ->get();
            }
        }

        if ($recomendados->count() < 8 && ! empty($cartPresentacionIds)) {
            $categoriaIds = Presentacion::whereIn('id', $cartPresentacionIds)
                ->with('producto')
                ->get()
                ->pluck('producto.categoria_id')
                ->unique();

            $fill = Producto::activos()
                ->whereIn('categoria_id', $categoriaIds)
                ->whereNotIn('id', $cartProductoIds)
                ->whereNotIn('id', $recomendados->pluck('id'))
                ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                ->inRandomOrder()
                ->take(8 - $recomendados->count())
                ->get();

            $recomendados = $recomendados->concat($fill);
        }

        return $recomendados;
    }

    /**
     * Throws a validation error if the desired quantity exceeds available stock.
     * Used when the customer is still building the cart (before an order exists).
     */
    public function assertStockDisponible(int $presentacionId, int $cantidadDeseada): void
    {
        if (! Configuracion::actual()->controlar_stock) {
            return;
        }

        $stock = Presentacion::whereKey($presentacionId)->value('stock') ?? 0;

        if ($cantidadDeseada > $stock) {
            throw ValidationException::withMessages([
                // Sin el número: cuántas unidades quedan es dato del sistema, y
                // el aviso era la forma de sacarlo de a uno.
                'cantidad' => $stock > 0
                    ? 'No nos queda esa cantidad. Probá pidiendo menos.'
                    : 'Este producto no tiene stock disponible.',
            ]);
        }
    }
}
