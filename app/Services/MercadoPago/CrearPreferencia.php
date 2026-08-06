<?php

namespace App\Services\MercadoPago;

use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoItem;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

/**
 * Arma el cobro en MercadoPago y devuelve la dirección a la que hay que mandar
 * al cliente para que pague (Checkout Pro).
 *
 * Se eligió Checkout Pro y no Bricks a propósito: así los datos de la tarjeta
 * nunca pasan por TiendaBase. Este motor se instala en servidores de terceros,
 * y hacerse cargo de datos de tarjeta en cada negocio instalado sería una
 * responsabilidad enorme a cambio de que el cliente no cambie de pantalla.
 */
class CrearPreferencia
{
    /**
     * La dirección de pago para este pedido.
     *
     * @throws RuntimeException si el negocio no tiene el cobro online en
     *                          condiciones; el llamador decide qué hacer con eso.
     */
    public function paraElPedido(Pedido $pedido): string
    {
        $config = Configuracion::actual();
        $token = $config->tokenMercadoPago();

        if ($token === null) {
            throw new RuntimeException('El negocio no tiene cargadas las credenciales de MercadoPago.');
        }

        // El token es estado global del SDK, así que se asigna en cada llamada
        // y no una vez al arrancar: si alguna vez se rota desde el panel, la
        // próxima preferencia ya sale con el nuevo.
        MercadoPagoConfig::setAccessToken($token);

        $preferencia = (new PreferenceClient)->create($this->cuerpoParaElPedido($pedido));

        if (blank($preferencia->init_point)) {
            throw new RuntimeException('MercadoPago no devolvió una dirección de pago para el pedido '.$pedido->id.'.');
        }

        return $preferencia->init_point;
    }

    /**
     * Lo que se le manda a MercadoPago. Es público a propósito: es una función
     * pura del pedido y es la que decide CUÁNTA plata se le va a cobrar al
     * cliente, así que tiene que poder verificarse sin pedirle nada a la red.
     *
     * @return array<string, mixed>
     */
    public function cuerpoParaElPedido(Pedido $pedido): array
    {
        $pedido->loadMissing('items.presentacion.producto', 'user');
        $vuelta = route('checkout.confirmacion', $pedido->id);

        return [
            'items' => $this->items($pedido),
            'payer' => [
                'name' => (string) ($pedido->datos_cliente['nombre'] ?? ''),
                'email' => (string) ($pedido->datos_cliente['email'] ?? ''),
            ],

            // El hilo que ata el pago con el pedido. Es lo que va a leer el
            // webhook para saber a qué pedido acreditarle la plata.
            'external_reference' => (string) $pedido->id,

            // Las tres vuelven al mismo lado: la pantalla de confirmación
            // muestra el estado real del pedido, no lo que diga la URL. Que el
            // navegador vuelva por "success" no prueba nada — el que prueba es
            // el webhook.
            'back_urls' => [
                'success' => $vuelta,
                'pending' => $vuelta,
                'failure' => $vuelta,
            ],
            'auto_return' => 'approved',

            // Sin notification_url todavía: la ruta que la recibe se construye
            // en la fase siguiente, y mandar acá una dirección que aún no
            // valida firmas sería peor que no mandar ninguna. El panel ya le
            // dice al negocio que configure la notificación desde MercadoPago,
            // que además es el camino que ellos recomiendan.

            // El pedido ya reservó stock, así que la preferencia vence junto
            // con él: sin esto, alguien podría pagar dos días después un pedido
            // que la tienda ya canceló y le devolvió el stock a otro cliente.
            'expires' => true,
            'expiration_date_to' => now()->addMinutes(Pedido::MINUTOS_PARA_PAGAR)->toIso8601String(),
        ];
    }

    /**
     * Un renglón por producto, con el precio que YA se le calculó al cliente
     * (mayorista, oferta o lista, según corresponda). No se recalcula nada acá:
     * el precio del pedido es el que vale, y recalcularlo abriría la puerta a
     * que MercadoPago cobre un número distinto del que el cliente aceptó.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(Pedido $pedido): array
    {
        return $pedido->items->map(fn ($item) => [
            'id' => (string) $item->presentacion_id,
            'title' => $this->titulo($item),
            'quantity' => (int) $item->cantidad,
            'unit_price' => (float) $item->precio_unitario,
            'currency_id' => 'ARS',
        ])->all();
    }

    private function titulo(PedidoItem $item): string
    {
        // El nullsafe va solo en la presentación: si esa fila desapareció, toda
        // la cadena se corta y cae en el respaldo. El producto detrás de una
        // presentación que existe no puede faltar (la clave foránea lo impide).
        $producto = $item->presentacion?->producto->nombre ?? 'Producto';
        $unidad = $item->presentacion?->unidad;

        // MercadoPago corta los títulos largos en su pantalla, así que el
        // nombre del producto va primero y la presentación después.
        return trim($producto.($unidad ? ' · '.$unidad : ''));
    }
}
