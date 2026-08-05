<?php

namespace App\Http\Requests\Cart;

use App\Models\Presentacion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Acá NO se exige que siga a la venta: si el negocio dio de baja un
            // producto que alguien tenía en el carrito, tiene que poder sacarlo
            // (cantidad 0). Que no se pueda pedir lo garantiza el checkout, que
            // arma la lista con presentaciones activas.
            'presentacion_id' => ['required', 'integer', 'exists:presentaciones,id'],
            // 0 borra el ítem; el tope superior evita pedidos absurdos cuando el
            // control de stock está apagado.
            'cantidad' => ['required', 'integer', 'min:0', 'max:'.Presentacion::MAXIMO_POR_PEDIDO],
        ];
    }
}
