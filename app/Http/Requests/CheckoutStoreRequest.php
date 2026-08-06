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
            // Solo se exige cuando el cliente es el que elige. Si el negocio no
            // cobra online, o lo exige siempre, lo que venga en este campo no
            // decide nada: lo decide pagaOnline().
            'forma_pago' => $this->clienteEligeComoPagar()
                ? ['required', Rule::in(['online', 'coordinar'])]
                : ['nullable'],
        ];
    }

    /** ¿Este checkout le ofrece al cliente elegir entre pagar ahora y coordinar? */
    public function clienteEligeComoPagar(): bool
    {
        $config = Configuracion::actual();

        return $config->puedeCobrarOnline() && ! $config->exigeCobroOnline();
    }

    /**
     * El único lugar que decide si este pedido se manda a pagar a MercadoPago.
     *
     * Que el cliente mande "online" no alcanza: si el negocio no tiene el cobro
     * en condiciones, se ignora y el pedido sigue el camino de siempre. Al
     * revés también — con el cobro obligatorio no importa qué mande el
     * formulario, porque eso se puede escribir a mano desde afuera.
     */
    public function pagaOnline(): bool
    {
        $config = Configuracion::actual();

        if (! $config->puedeCobrarOnline()) {
            return false;
        }

        return $config->exigeCobroOnline() || $this->input('forma_pago') === 'online';
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
