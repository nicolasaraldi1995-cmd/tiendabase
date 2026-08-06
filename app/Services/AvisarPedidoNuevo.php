<?php

namespace App\Services;

use App\Mail\PedidoNuevoMail;
use App\Models\Configuracion;
use App\Models\Pedido;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Le avisa al negocio que tiene un pedido nuevo para atender.
 *
 * Vive acá y no en un observer del modelo a propósito: los pedidos que carga
 * el propio negocio desde el panel no tienen que avisarle a sí mismo. Los que
 * sí avisan son dos caminos distintos —el que coordina el pago, que avisa al
 * confirmar, y el que paga online, que avisa recién cuando la plata está—, y
 * por eso el aviso no puede estar duplicado en cada uno.
 */
class AvisarPedidoNuevo
{
    public function __invoke(Pedido $pedido): void
    {
        $destino = Configuracion::actual()->email_avisos;

        if (! $destino) {
            return;
        }

        // Un mail que no sale no puede voltear un pedido ya confirmado ni un
        // pago ya acreditado: se registra y se sigue.
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
}
