<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Pedido;
use App\Services\MercadoPago\AcreditarPago;
use App\Services\MercadoPago\ConsultarPago;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    public function __invoke(Request $request, ConsultarPago $consultarPago, AcreditarPago $acreditar): Response
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

        // La comprobación del monto y la idempotencia viven adentro: es la
        // misma acreditación que usa la reconciliación.
        $acreditar($pedido, $pago);

        return response('', 200);
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
