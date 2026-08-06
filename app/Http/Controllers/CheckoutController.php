<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutStoreRequest;
use App\Mail\PedidoNuevoMail;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cartService) {}

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

        try {
            $pedido = DB::transaction(function () use ($items, $total, $user, $request) {
                $pedido = Pedido::create([
                    'user_id' => $user->id,
                    'estado' => 'pending',
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

        $this->avisarAlNegocio($pedido);

        return redirect()->route('checkout.confirmacion', $pedido->id);
    }

    /**
     * Aviso de pedido nuevo al dueño (email configurado en el panel). Va acá y
     * no en un observer del modelo a propósito: los pedidos que carga el propio
     * negocio desde el panel no tienen que disparar un aviso a sí mismo.
     *
     * El envío no puede voltear un pedido ya confirmado, así que si el mail
     * falla (servidor caído, credenciales mal) se registra y se sigue.
     */
    private function avisarAlNegocio(Pedido $pedido): void
    {
        $destino = Configuracion::actual()->email_avisos;

        if (! $destino) {
            return;
        }

        try {
            $pedido->load('items.presentacion.producto');
            Mail::to($destino)->send(new PedidoNuevoMail($pedido));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el aviso de pedido nuevo', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function confirmacion(Pedido $pedido)
    {
        $this->authorize('view', $pedido);

        $pedido->load([
            'items.presentacion.producto.marca',
            'items.presentacion.producto.categoria',
            'items.presentacion.producto.etiquetas',
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
