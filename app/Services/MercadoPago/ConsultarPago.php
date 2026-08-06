<?php

namespace App\Services\MercadoPago;

use App\Models\Configuracion;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPSearchRequest;
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
        $this->autenticar();

        return $this->comoArreglo((new PaymentClient)->get((int) $paymentId));
    }

    /**
     * Busca si este pedido tiene un pago APROBADO en MercadoPago.
     *
     * Es la red de seguridad por si el aviso nunca llegó: sin esto, un webhook
     * perdido —servidor dormido, corte de red, MercadoPago agotando reintentos—
     * terminaría en un pedido cancelado por falta de pago que en realidad se
     * cobró. Cancelar una venta ya cobrada es el peor error posible de todo
     * este circuito, así que se pregunta antes de cancelar, siempre.
     *
     * @return array{id: string, estado: string, monto: float, pedido_id: string|null}|null
     */
    public function aprobadoDelPedido(int $pedidoId): ?array
    {
        $this->autenticar();

        $busqueda = (new PaymentClient)->search(new MPSearchRequest(10, 0, [
            'external_reference' => (string) $pedidoId,
            'status' => 'approved',
        ]));

        foreach ($busqueda->results ?? [] as $resultado) {
            $pago = $this->comoArreglo($resultado);

            // El filtro de estado lo aplica MercadoPago, pero se vuelve a
            // mirar acá: es la condición de la que depende que se acredite.
            if ($pago['estado'] === 'approved') {
                return $pago;
            }
        }

        return null;
    }

    private function autenticar(): void
    {
        $token = Configuracion::actual()->tokenMercadoPago();

        if ($token === null) {
            throw new RuntimeException('El negocio no tiene cargadas las credenciales de MercadoPago.');
        }

        MercadoPagoConfig::setAccessToken($token);
    }

    /**
     * La respuesta de MercadoPago reducida a lo único que necesitamos. Devolver
     * un arreglo y no el objeto del SDK mantiene al resto del sistema —y a los
     * tests— independiente de la forma de su API.
     *
     * @param  object|array<string, mixed>  $pago
     * @return array{id: string, estado: string, monto: float, pedido_id: string|null}
     */
    private function comoArreglo(object|array $pago): array
    {
        $leer = fn (string $campo) => is_array($pago) ? ($pago[$campo] ?? null) : ($pago->{$campo} ?? null);

        $referencia = $leer('external_reference');

        return [
            'id' => (string) $leer('id'),
            'estado' => (string) $leer('status'),
            'monto' => (float) $leer('transaction_amount'),
            // Lo que atamos al crear la preferencia: el id del pedido.
            'pedido_id' => $referencia !== null ? (string) $referencia : null,
        ];
    }
}
