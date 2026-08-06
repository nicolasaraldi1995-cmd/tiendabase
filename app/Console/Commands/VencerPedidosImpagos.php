<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\MercadoPago\AcreditarPago;
use App\Services\MercadoPago\ConsultarPago;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('pedidos:vencer-impagos')]
#[Description('Cierra los pedidos que quedaron esperando un pago. Antes de cancelar le pregunta a MercadoPago si en realidad se pagó.')]
/**
 * El pedido que se manda a pagar reserva stock desde el minuto cero. Si el
 * cliente abandona, esas unidades quedarían retenidas para siempre y el
 * catálogo se vaciaría solo. Este comando las suelta.
 *
 * El orden es lo importante: PRIMERO se le pregunta a MercadoPago si el pedido
 * tiene un pago aprobado, y recién si no lo tiene se cancela. Un aviso perdido
 * —servidor dormido, corte de red, reintentos agotados— haría que un pedido
 * cobrado de verdad llegue acá como impago, y cancelar una venta ya cobrada es
 * el peor error que puede cometer todo este circuito. Por eso esta función es
 * a la vez el vencimiento y la reconciliación: son la misma pregunta.
 */
class VencerPedidosImpagos extends Command
{
    public function handle(ConsultarPago $consultar, AcreditarPago $acreditar): int
    {
        $vencidos = Pedido::where('estado', 'awaiting_payment')
            ->where('created_at', '<', now()->subMinutes(Pedido::MINUTOS_PARA_PAGAR))
            ->get();

        if ($vencidos->isEmpty()) {
            $this->info('No hay pedidos esperando pago que hayan vencido.');

            return self::SUCCESS;
        }

        $acreditados = 0;
        $cancelados = 0;
        $postergados = 0;

        foreach ($vencidos as $pedido) {
            // Guard por pedido: uno que falle no puede frenar a los demás ni
            // dejar el resto del catálogo con el stock trabado.
            try {
                $pago = $consultar->aprobadoDelPedido($pedido->id);

                if ($pago !== null) {
                    $acreditar($pedido, $pago);
                    $acreditados++;
                    $this->warn("Pedido #{$pedido->id}: estaba pagado y el aviso nunca llegó. Acreditado.");

                    continue;
                }

                $this->cancelar($pedido);
                $cancelados++;
            } catch (\Throwable $e) {
                // Si no se pudo preguntar, NO se cancela: vale mucho más
                // reintentar en la próxima corrida que cancelar a ciegas un
                // pedido que quizá está pago.
                $postergados++;
                Log::warning('No se pudo resolver un pedido impago; queda para la próxima corrida', [
                    'pedido_id' => $pedido->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Revisados {$vencidos->count()}: {$cancelados} cancelados, {$acreditados} acreditados, {$postergados} postergados.");

        return self::SUCCESS;
    }

    /** Cancela y devuelve al catálogo las unidades que este pedido tenía reservadas. */
    private function cancelar(Pedido $pedido): void
    {
        DB::transaction(function () use ($pedido) {
            $pedido->restaurarStock();
            $pedido->update(['estado' => 'canceled']);
        });

        $this->line("Pedido #{$pedido->id}: sin pago, cancelado y stock devuelto.");
    }
}
