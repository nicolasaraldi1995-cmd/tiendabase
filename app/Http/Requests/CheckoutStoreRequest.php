<?php

namespace App\Http\Requests;

use App\Models\Configuracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutStoreRequest extends FormRequest
{
    public function rules(): array
    {
        // Si el negocio no reparte, "envío" no es una opción válida: antes se
        // podía mandar igual desde afuera del formulario.
        $entregas = Configuracion::actual()->hace_envios ? ['retiro', 'envio'] : ['retiro'];

        return [
            'entrega' => ['required', Rule::in($entregas)],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Un pedido a domicilio sin domicilio le llega al negocio como algo para
     * entregar sin saber dónde ni a quién llamar. La dirección vive en el
     * perfil del cliente, así que se comprueba ahí y se lo manda a completarlo.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->input('entrega') !== 'envio') {
                    return;
                }

                $usuario = $this->user();

                if (blank($usuario->direccion) || blank($usuario->celular)) {
                    $validator->errors()->add(
                        'entrega',
                        'Para el envío a domicilio necesitamos tu dirección y tu teléfono. Completalos en "Mi cuenta" y volvé a confirmar.',
                    );
                }
            },
        ];
    }
}
