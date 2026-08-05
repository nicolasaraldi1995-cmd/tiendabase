<?php

namespace App\Http\Requests\Cart;

use App\Models\Presentacion;
use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // No alcanza con que exista: tiene que estar a la venta. Un producto
            // apagado en el panel seguía entrando al carrito por link directo, y
            // recién desaparecía sin aviso al dibujar la lista.
            'presentacion_id' => ['required', 'integer', function (string $atributo, mixed $valor, callable $fallar) {
                if (! Presentacion::estaALaVenta($valor)) {
                    $fallar(trans('validation.exists'));
                }
            }],
            // Con el control de stock apagado nada acotaba la cantidad: un tope
            // alto pero sensato evita pedidos absurdos por un cero de más.
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.Presentacion::MAXIMO_POR_PEDIDO],
        ];
    }
}
