<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\AvisarPedidoNuevo;
use App\Services\MercadoPago\ConsultarPago;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;

/**
 * Lo único en toda la tienda que puede marcar un pedido como pagado.
 *
 * La regla que ordena todo esto: la vuelta del navegador es para el usuario, y
 * este aviso firmado es para la base de datos. El cliente puede cerrar el
 * navegador justo antes de volver de MercadoPago —y su pago igual tiene que
 * acreditarse—, y al revés, cualquiera puede escribir a mano la dirección de
 * "pago exitoso". Por eso esa pantalla no cambia ningún estado y esta sí.
 *
 * Qué contesta y por qué:
 *   401  firma que no cierra. No se toca nada.
 *   200  todo lo demás que ya está resuelto (incluido "no me interesa").
 *   5xx  algo se rompió de nuestro lado. Es a propósito: MercadoPago reintenta
 *        (15 min, 30 min, 6 h, 48 h) y ese reintento es la red de seguridad.
 */
class MercadoPagoWebhookController extends Controller
{
    /** Diferencia máxima tolerada entre lo pagado y el total, en pesos. */
    private const TOLERANCIA_DE_MONTO = 0.01;

    public function __invoke(Request $request, ConsultarPago $consultarPago, AvisarPedidoNuevo $avisar): Response
    {
        $secreto = Configuracion::actual()->secretoWebhookMercadoPago();

        // Sin secreto no hay forma de saber si esto vino de MercadoPago. Se
        // contesta 200 igual, y no un error, para que no se pasen dos días
        // reintentando contra una tienda que ni siquiera cobra online.
        if ($secreto === null) {
            return response('', 200);
        }

        $idDelRecurso = $this->idDelRecurso($request);

        try {
            WebhookSignatureValidator::validate(
                $request->header('x-signature'),
                $request->header('x-request-id'),
                $idDelRecurso,
                $secreto,
                // Sin tolerancia de tiempo a propósito: los reintentos de
                // MercadoPago llegan hasta 48 h después, y rechazarlos por
                // viejos perdería justo el aviso que más falta hace. Repetir un
                // aviso viejo tampoco sirve de nada, porque el estado se
                // vuelve a consultar y el alta del pago es idempotente.
            );
        } catch (InvalidWebhookSignatureException|\InvalidArgumentException $e) {
            Log::warning('Aviso de MercadoPago con firma inválida', [
                'request_id' => $request->header('x-request-id'),
                'motivo' => $e->getMessage(),
            ]);

            return response('', 401);
        }

        // Solo interesan los avisos de pago; MercadoPago manda varios tipos.
        if ($this->tipo($request) !== 'payment' || $idDelRecurso === null) {
            return response('', 200);
        }

        // Si esto explota, que explote: el 5xx hace que MercadoPago reintente.
        $pago = $consultarPago->porId($idDelRecurso);

        if ($pago['estado'] !== 'approved') {
            return response('', 200);
        }

        $pedido = $pago['pedido_id'] !== null ? Pedido::find($pago['pedido_id']) : null;

        if (! $pedido) {
            Log::warning('Pago aprobado sin pedido que le corresponda', [
                'mp_payment_id' => $pago['id'],
                'external_reference' => $pago['pedido_id'],
            ]);

            return response('', 200);
        }

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

            return response('', 200);
        }

        $this->acreditar($pedido, $pago, $avisar);

        return response('', 200);
    }

    /**
     * Registra el pago y mete el pedido en el circuito del negocio.
     *
     * @param  array{id: string, estado: string, monto: float, pedido_id: string|null}  $pago
     */
    private function acreditar(Pedido $pedido, array $pago, AvisarPedidoNuevo $avisar): void
    {
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
            // MercadoPago reintenta el mismo aviso por diseño. El índice único
            // de mp_payment_id es lo que lo convierte en una operación inocua.
            return;
        }

        // Fuera de la transacción y solo cuando se acreditó de verdad: así el
        // negocio recibe un aviso por pedido, no uno por reintento.
        $avisar($pedido);
    }

    /**
     * El id del recurso avisado, que es con lo que se firma el aviso.
     *
     * MercadoPago lo manda como `data.id` en la dirección, pero PHP convierte
     * el punto en guión bajo al parsear la query, así que llega como `data_id`.
     * Se miran también las otras dos formas que usan según el tipo de aviso.
     */
    private function idDelRecurso(Request $request): ?string
    {
        foreach ([$request->query('data_id'), $request->query('id'), $request->input('data.id')] as $valor) {
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                return trim((string) $valor);
            }
        }

        return null;
    }

    private function tipo(Request $request): ?string
    {
        foreach ([$request->query('type'), $request->query('topic'), $request->input('type')] as $valor) {
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }
}
