<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutStoreRequest;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Services\AvisarPedidoNuevo;
use App\Services\CartService;
use App\Services\MercadoPago\CrearPreferencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CrearPreferencia $preferencias,
        private AvisarPedidoNuevo $avisar,
    ) {}

    public function index()
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('productos.index');
        }

        $items = $this->cartService->resolveItems($cart);
        $total = collect($items)->sum('subtotal');
        $user = auth()->user();

        $recomendados = $this->cartService->recomendadosPara($user, array_keys($cart));

        return Inertia::render('Checkout', [
            'items' => $items,
            'avisos' => $this->cartService->avisosPara($items, $user),
            'total' => $total,
            'envioGratis' => Configuracion::actual()->hayEnvioGratis((float) $total),
            'faltaParaElMinimo' => max(0, Configuracion::actual()->pedidoMinimoPara($user) - $total),
            'recomendados' => $recomendados,
            'pagoOnline' => [
                'disponible' => Configuracion::actual()->puedeCobrarOnline(),
                'obligatorio' => Configuracion::actual()->exigeCobroOnline(),
            ],
            'cliente' => [
                'nombre' => $user->name,
                'negocio' => $user->negocio,
                'tipo_cliente' => $user->tipo_cliente,
                'email' => $user->email,
                'celular' => $user->celular,
                'direccion' => $user->direccion,
                'ciudad' => $user->ciudad,
                'provincia' => $user->provincia,
            ],
        ]);
    }

    public function store(CheckoutStoreRequest $request)
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('productos.index');
        }

        $items = $this->cartService->resolveItems($cart);
        $total = collect($items)->sum('subtotal');
        $user = auth()->user();

        $minimo = Configuracion::actual()->pedidoMinimoPara($user);
        if ($minimo > 0 && $total < $minimo) {
            return back()->withErrors([
                'total' => 'El pedido mínimo es de $'.number_format($minimo, 0, ',', '.')
                    .'. Te faltan $'.number_format($minimo - $total, 0, ',', '.').' para poder confirmarlo.',
            ]);
        }

        $pagaOnline = $request->pagaOnline();

        try {
            $pedido = DB::transaction(function () use ($items, $total, $user, $request, $pagaOnline) {
                $pedido = Pedido::create([
                    'user_id' => $user->id,
                    // El que va a pagar online nace esperando el pago: el stock
                    // ya queda reservado, pero el pedido no entra al circuito
                    // del negocio hasta que la plata esté.
                    'estado' => $pagaOnline ? 'awaiting_payment' : 'pending',
                    'total' => $total,
                    'datos_cliente' => [
                        'nombre' => $user->name,
                        'negocio' => $user->negocio,
                        'tipo_cliente' => $user->tipo_cliente,
                        'email' => $user->email,
                        'celular' => $user->celular,
                        'direccion' => $user->direccion,
                        'ciudad' => $user->ciudad,
                        'provincia' => $user->provincia,
                        'entrega' => $request->entrega,
                        'notas' => $request->notas,
                    ],
                ]);

                foreach ($items as $item) {
                    // PedidoItemObserver valida y descuenta el stock al crear cada item.
                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'presentacion_id' => $item['presentacion_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                return $pedido;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        session()->forget('cart');

        if ($pagaOnline) {
            return $this->mandarAPagar($pedido);
        }

        ($this->avisar)($pedido);

        return redirect()->route('checkout.confirmacion', $pedido->id);
    }

    /**
     * Manda al cliente a la pantalla de pago de MercadoPago.
     *
     * Si eso falla —MercadoPago caído, credenciales vencidas, lo que sea— el
     * pedido NO se pierde: ya existe y ya reservó stock, así que vuelve al
     * circuito de siempre y se coordina el pago a mano. Perder una venta
     * confirmada porque se cayó un servicio de terceros sería el peor final
     * posible para este camino.
     *
     * El aviso al negocio queda para cuando el pago se acredite: avisarle de un
     * pedido que todavía nadie pagó lo haría preparar mercadería por nada.
     */
    private function mandarAPagar(Pedido $pedido)
    {
        try {
            return redirect()->away($this->preferencias->paraElPedido($pedido));
        } catch (\Throwable $e) {
            Log::error('No se pudo abrir el pago online', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            $pedido->update(['estado' => 'pending']);
            ($this->avisar)($pedido);

            return redirect()
                ->route('checkout.confirmacion', $pedido->id)
                ->with('aviso', 'No pudimos abrir el pago online, pero tu pedido quedó confirmado. Nos vamos a comunicar para coordinar el pago.');
        }
    }

    public function confirmacion(Pedido $pedido)
    {
        $this->authorize('view', $pedido);

        $pedido->load([
            'items.presentacion.producto.marca',
            'items.presentacion.producto.categoria',
            'items.presentacion.producto.etiquetas' => fn ($e) => $e->activas(),
        ]);

        // Los mismos avisos que vio en el carrito, para que la confirmación no
        // le prometa algo distinto de lo que le acaba de decir la tienda.
        $conAviso = $pedido->items
            ->pluck('presentacion.producto.etiquetas')
            ->flatten()
            ->filter(fn ($e) => $e->activo && filled($e->aviso))
            ->unique('aviso')
            ->map(fn ($e) => ['texto' => $e->aviso])
            ->values();

        return Inertia::render('CheckoutConfirmacion', [
            'pedido' => $pedido,
            'avisos' => auth()->user()?->omite_avisos ? [] : $conAviso,
        ]);
    }
}
