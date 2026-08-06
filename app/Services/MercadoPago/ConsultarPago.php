<?php

namespace App\Services\MercadoPago;

use App\Models\Configuracion;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

/**
 * Le pregunta a MercadoPago cómo terminó un pago.
 *
 * Existe para que el webhook NUNCA le crea al cuerpo de la notificación. Ese
 * cuerpo llega por internet y solo dice "mirá el pago 123": el estado y el
 * monto reales se piden acá, con el token del negocio. Si se confiara en lo
 * que viene en la notificación, cualquiera que consiga una firma válida de un
 * pago de un peso podría hacer pasar por pagado un pedido de cien mil.
 */
class ConsultarPago
{
    /**
     * @return array{id: string, estado: string, monto: float, pedido_id: string|null}
     *
     * @throws RuntimeException si no hay credenciales; cualquier otro error de
     *                          red o de la API sube tal cual, para que el
     *                          webhook devuelva 5xx y MercadoPago reintente.
     */
    public function porId(string $paymentId): array
    {
        $token = Configuracion::actual()->tokenMercadoPago();

        if ($token === null) {
            throw new RuntimeException('El negocio no tiene cargadas las credenciales de MercadoPago.');
        }

        MercadoPagoConfig::setAccessToken($token);

        $pago = (new PaymentClient)->get((int) $paymentId);

        return [
            'id' => (string) $pago->id,
            'estado' => (string) $pago->status,
            'monto' => (float) $pago->transaction_amount,
            // Lo que atamos al crear la preferencia: el id del pedido.
            'pedido_id' => $pago->external_reference !== null ? (string) $pago->external_reference : null,
        ];
    }
}
