<?php

namespace App\Services\MercadoPago;

use App\Models\Pago;
use App\Models\Pedido;
use App\Services\AvisarPedidoNuevo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registra un pago de MercadoPago contra su pedido y lo mete en el circuito
 * del negocio.
 *
 * Vive acá y no adentro del webhook porque hay dos caminos que acreditan: el
 * aviso de MercadoPago, que es el normal, y la reconciliación, que es la red
 * por si ese aviso nunca llegó. Duplicar esta lógica en los dos significaría
 * que arreglar uno deja el otro roto — y lo que está en juego es plata.
 */
class AcreditarPago
{
    /** Diferencia máxima tolerada entre lo pagado y el total, en pesos. */
    private const TOLERANCIA_DE_MONTO = 0.01;

    public function __construct(private AvisarPedidoNuevo $avisar) {}

    /**
     * @param  array{id: string, estado: string, monto: float, pedido_id: string|null}  $pago
     * @return bool true si se acreditó en esta llamada. false significa que no
     *              había nada que hacer (ya estaba acreditado) o que no se
     *              debía hacer (el monto no cierra) — nunca que se perdió algo.
     */
    public function __invoke(Pedido $pedido, array $pago): bool
    {
        if (abs($pago['monto'] - (float) $pedido->total) > self::TOLERANCIA_DE_MONTO) {
            // Nunca debería pasar: el precio lo fija el servidor al crear la
            // preferencia. Si pasa, algo se manipuló o algo está mal calculado,
            // y en los dos casos acreditar sería peor que no hacerlo.
            Log::error('Pago aprobado por un monto distinto al del pedido', [
                'pedido_id' => $pedido->id,
                'mp_payment_id' => $pago['id'],
                'pagado' => $pago['monto'],
                'esperado' => (float) $pedido->total,
            ]);

            return false;
        }

        try {
            DB::transaction(function () use ($pedido, $pago) {
                Pago::create([
                    'pedido_id' => $pedido->id,
                    'user_id' => $pedido->user_id,
                    'monto' => $pago['monto'],
                    'metodo' => 'mercadopago',
                    'fecha' => now(),
                    'mp_payment_id' => $pago['id'],
                ]);

                // Solo si venía esperando: un pedido que el negocio ya movió a
                // "en preparación" no puede volver para atrás por un reintento.
                if ($pedido->esperaPago()) {
                    $pedido->update(['estado' => 'pending']);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Este pago ya estaba acreditado. Es el caso NORMAL, no el raro:
            // MercadoPago reintenta el mismo aviso por diseño, y encima la
            // reconciliación puede llegar al mismo pago por otro lado. El
            // índice único de mp_payment_id es lo que lo vuelve inocuo.
            return false;
        }

        // Fuera de la transacción y solo cuando se acreditó de verdad: así el
        // negocio recibe un aviso por pedido, no uno por reintento.
        ($this->avisar)($pedido);

        return true;
    }
}
