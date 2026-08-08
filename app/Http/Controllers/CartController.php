<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddComboToCartRequest;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\RemoveFromCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Combo;
use App\Models\Presentacion;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index()
    {
        $cart = session('cart', []);
        $items = $this->cartService->resolveItems($cart);
        $user = auth()->user();

        $recomendados = $this->cartService->recomendadosPara($user, array_keys($cart));

        $pedidoAnterior = null;
        if ($user) {
            $ultimo = $user->pedidos()
                ->whereNotIn('estado', ['canceled', 'draft'])
                ->with('items.presentacion.producto.marca')
                ->latest()
                ->first();

            if ($ultimo) {
                $itemsValidos = $ultimo->items
                    ->filter(fn ($it) => $it->presentacion?->producto !== null)
                    ->map(fn ($it) => [
                        'presentacion_id' => $it->presentacion_id,
                        'producto_id' => $it->presentacion->producto_id,
                        'nombre' => $it->presentacion->producto->nombre,
                        'marca' => $it->presentacion->producto->marca->nombre ?? 'Sin marca',
                        'unidad' => $it->presentacion->unidad,
                        'imagen' => $it->presentacion->imagen_url ?? $it->presentacion->producto->imagen_url,
                    ])
                    ->values();

                // Si todos los productos del pedido anterior ya no existen, no hay nada
                // real para comparar: mejor ocultar la función que decir "tenés todo".
                if ($itemsValidos->isNotEmpty()) {
                    $pedidoAnterior = [
                        'id' => $ultimo->id,
                        'fecha' => $ultimo->created_at->format('d/m/Y'),
                        'items' => $itemsValidos,
                    ];
                }
            }
        }

        return Inertia::render('Cart', [
            'items' => $items,
            'avisos' => $this->cartService->avisosPara($items, $user),
            'total' => collect($items)->sum('subtotal'),
            'recomendados' => $recomendados,
            'pedidoAnterior' => $pedidoAnterior,
        ]);
    }

    public function add(AddToCartRequest $request)
    {
        $cart = session('cart', []);
        $id = (string) $request->presentacion_id;
        $nuevaCantidad = ($cart[$id] ?? 0) + $request->cantidad;

        // El tope del formulario es por petición, y acá se suma a lo que ya
        // había: agregando 9999 tres veces el carrito quedaba en 29.999. Se
        // revisa el acumulado, con el mismo aviso que da cambiar la cantidad.
        if ($nuevaCantidad > Presentacion::MAXIMO_POR_PEDIDO) {
            throw ValidationException::withMessages([
                'cantidad' => 'No se pueden pedir más de '.Presentacion::MAXIMO_POR_PEDIDO.' unidades de una vez.',
            ]);
        }

        $this->cartService->assertStockDisponible($request->presentacion_id, $nuevaCantidad);

        $cart[$id] = $nuevaCantidad;
        session(['cart' => $cart]);

        return back();
    }

    public function update(UpdateCartRequest $request)
    {
        $cart = session('cart', []);
        $id = (string) $request->presentacion_id;

        if ($request->cantidad <= 0) {
            unset($cart[$id]);
        } else {
            $this->cartService->assertStockDisponible($request->presentacion_id, $request->cantidad);
            $cart[$id] = $request->cantidad;
        }

        session(['cart' => $cart]);

        return back();
    }

    /**
     * Un combo es una bolsa de presentaciones, así que tiene que pasar por los
     * mismos controles que agregarlas de a una. Se salteaba los tres: entraba un
     * combo apagado, entraban presentaciones que ya no están a la venta (y
     * después desaparecían solas del carrito, sin explicación), y no había tope
     * de cantidad — tocando tres veces se llegaba a un total que no entra en la
     * columna y el checkout devolvía un error 500 del que el cliente no salía.
     */
    public function addCombo(AddComboToCartRequest $request)
    {
        $combo = Combo::activos()->with('items.presentacion')->find((int) $request->combo_id);

        if (! $combo) {
            throw ValidationException::withMessages([
                'combo_id' => trans('validation.exists', ['attribute' => trans('validation.attributes.combo_id')]),
            ]);
        }

        $cart = session('cart', []);

        foreach ($combo->items as $item) {
            if (! Presentacion::estaALaVenta($item->presentacion_id)) {
                throw ValidationException::withMessages([
                    'combo_id' => 'Este combo tiene productos que ya no están a la venta. Avisale al negocio.',
                ]);
            }

            $id = (string) $item->presentacion_id;
            $nuevaCantidad = ($cart[$id] ?? 0) + $item->cantidad;

            if ($nuevaCantidad > Presentacion::MAXIMO_POR_PEDIDO) {
                throw ValidationException::withMessages([
                    'combo_id' => 'No se pueden pedir más de '.Presentacion::MAXIMO_POR_PEDIDO.' unidades de una vez.',
                ]);
            }

            $this->cartService->assertStockDisponible($item->presentacion_id, $nuevaCantidad);
            $cart[$id] = $nuevaCantidad;
        }

        session(['cart' => $cart]);

        return back();
    }

    public function remove(RemoveFromCartRequest $request)
    {
        $cart = session('cart', []);
        unset($cart[(string) $request->presentacion_id]);
        session(['cart' => $cart]);

        return back();
    }

    /**
     * Saca del carrito todo lo que lleva una etiqueta. Es el botón que
     * acompaña al aviso ("Quitar los que son bajo pedido"). Antes era un caso
     * fijo de la cadena de frío escrito acá adentro.
     */
    public function removeEtiqueta(Request $request)
    {
        $request->validate(['etiqueta_id' => ['required', 'integer', 'exists:etiquetas,id']]);

        $cart = session('cart', []);

        if (empty($cart)) {
            return back();
        }

        $ids = Presentacion::whereIn('id', array_keys($cart))
            ->whereHas('producto.etiquetas', fn ($q) => $q->where('etiquetas.id', $request->etiqueta_id))
            ->pluck('id');

        foreach ($ids as $id) {
            unset($cart[(string) $id]);
        }

        session(['cart' => $cart]);

        return back();
    }
}
