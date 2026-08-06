<?php

namespace Tests\Feature;

use App\Mail\PedidoNuevoMail;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\User;
use App\Services\MercadoPago\ConsultarPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * El webhook: lo ÚNICO que puede marcar un pedido como pagado.
 *
 * La firma se genera de verdad en cada test (mismo HMAC que hace MercadoPago),
 * así que no se está probando contra un validador de mentira: se prueba contra
 * el del SDK, con firmas reales y con firmas mal hechas.
 */
class CobroOnlineWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'un-secreto-de-webhook';

    private const URL = '/webhooks/mercadopago';

    private function conCobroOnline(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'TEST-token',
            'mp_webhook_secret' => self::SECRETO,
            'email_avisos' => 'dueno@negocio.test',
        ]);
    }

    /** Un pedido esperando pago, con stock ya reservado. */
    private function pedidoEsperandoPago(float $total = 3000): Pedido
    {
        $presentacion = Presentacion::factory()->create(['precio' => $total, 'stock' => 10, 'activo' => true]);
        $cliente = User::factory()->create(['role' => 'cliente']);

        $pedido = Pedido::factory()->create([
            'user_id' => $cliente->id,
            'estado' => 'awaiting_payment',
            'total' => $total,
        ]);

        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 1,
            'precio_unitario' => $total,
            'subtotal' => $total,
        ]);

        return $pedido->fresh();
    }

    /** Reemplaza la consulta a MercadoPago por una respuesta fija. */
    private function mercadoPagoResponde(string $estado, float $monto, ?string $pedidoId, string $pagoId = '112233'): void
    {
        $this->instance(ConsultarPago::class, new class($estado, $monto, $pedidoId, $pagoId) extends ConsultarPago
        {
            public function __construct(
                private string $estado,
                private float $monto,
                private ?string $pedidoId,
                private string $pagoId,
            ) {}

            public function porId(string $paymentId): array
            {
                return [
                    'id' => $this->pagoId,
                    'estado' => $this->estado,
                    'monto' => $this->monto,
                    'pedido_id' => $this->pedidoId,
                ];
            }
        });
    }

    /**
     * La misma cuenta que hace MercadoPago para firmar. Si el controlador arma
     * el manifiesto distinto, estos tests fallan — que es exactamente lo que
     * tienen que hacer.
     */
    private function avisar(string $dataId = '112233', ?string $firma = null, string $requestId = 'req-abc'): TestResponse
    {
        $ts = (string) now()->timestamp;
        $manifiesto = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $hash = $firma ?? hash_hmac('sha256', $manifiesto, self::SECRETO);

        return $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$hash}",
            'x-request-id' => $requestId,
        ])->postJson(self::URL."?data.id={$dataId}&type=payment", [
            'action' => 'payment.updated',
            'type' => 'payment',
            'data' => ['id' => $dataId],
        ]);
    }

    // ─── Lo que NO tiene que pasar ────────────────────────────────────────

    public function test_una_firma_invalida_no_toca_nada(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago();
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id);

        $this->avisar(firma: str_repeat('a', 64))->assertStatus(401);

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
        $this->assertSame(0, Pago::count());
    }

    public function test_sin_firma_no_toca_nada(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago();
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id);

        $this->postJson(self::URL.'?data.id=112233&type=payment', ['type' => 'payment'])
            ->assertStatus(401);

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
    }

    /**
     * Firmar con OTRO secreto es lo que haría alguien que adivinó la dirección
     * del webhook pero no tiene la clave del negocio.
     */
    public function test_una_firma_hecha_con_otro_secreto_no_sirve(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago();
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id);

        $ts = (string) now()->timestamp;
        $hash = hash_hmac('sha256', "id:112233;request-id:req-abc;ts:{$ts};", 'el-secreto-equivocado');

        $this->withHeaders(['x-signature' => "ts={$ts},v1={$hash}", 'x-request-id' => 'req-abc'])
            ->postJson(self::URL.'?data.id=112233&type=payment', ['type' => 'payment'])
            ->assertStatus(401);

        $this->assertSame(0, Pago::count());
    }

    /**
     * El test que cuida la plata de verdad: el monto se comprueba contra el
     * pedido. Sin esto, un pago real de un peso acreditaría un pedido de miles.
     */
    public function test_un_monto_que_no_coincide_no_acredita_nada(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago(50000);
        $this->mercadoPagoResponde('approved', 1.00, (string) $pedido->id);

        $this->avisar()->assertOk();

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
        $this->assertSame(0, Pago::count());
    }

    public function test_un_pago_no_aprobado_no_acredita_nada(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago();

        foreach (['pending', 'in_process', 'rejected', 'cancelled'] as $estado) {
            $this->mercadoPagoResponde($estado, 3000, (string) $pedido->id);
            $this->avisar()->assertOk();

            $this->assertSame('awaiting_payment', $pedido->fresh()->estado, "El estado «{$estado}» acreditó el pedido.");
            $this->assertSame(0, Pago::count());
        }
    }

    public function test_un_pedido_que_no_existe_no_rompe(): void
    {
        $this->conCobroOnline();
        $this->mercadoPagoResponde('approved', 3000, '999999');

        $this->avisar()->assertOk();

        $this->assertSame(0, Pago::count());
    }

    /**
     * Una tienda sin el secreto cargado no puede verificar nada. Contesta 200
     * igual para que MercadoPago no reintente durante días contra un negocio
     * que ni siquiera cobra online.
     */
    public function test_sin_secreto_configurado_contesta_ok_sin_hacer_nada(): void
    {
        $pedido = $this->pedidoEsperandoPago();
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id);

        $this->avisar()->assertOk();

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
        $this->assertSame(0, Pago::count());
    }

    // ─── Lo que SÍ tiene que pasar ────────────────────────────────────────

    public function test_un_pago_aprobado_acredita_el_pedido_y_avisa_al_negocio(): void
    {
        Mail::fake();
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago(3000);
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id, '55667788');

        $this->avisar()->assertOk();

        $this->assertSame('pending', $pedido->fresh()->estado);

        $pago = Pago::firstOrFail();
        $this->assertSame('mercadopago', $pago->metodo);
        $this->assertSame('55667788', $pago->mp_payment_id);
        $this->assertEquals(3000, $pago->monto);
        $this->assertSame($pedido->id, $pago->pedido_id);
        $this->assertSame($pedido->user_id, $pago->user_id);

        // Recién ahora el negocio se entera: la plata está.
        Mail::assertSent(PedidoNuevoMail::class);
    }

    /**
     * EL test de esta fase. MercadoPago reintenta la misma notificación por
     * diseño (15 min, 30 min, 6 h, 48 h), así que recibir el mismo aviso varias
     * veces es el caso normal, no el raro. Dos pagos serían plata contada dos
     * veces en el saldo del cliente y en la caja del negocio.
     */
    public function test_el_mismo_aviso_repetido_acredita_una_sola_vez(): void
    {
        Mail::fake();
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago(3000);
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id, '55667788');

        $this->avisar()->assertOk();
        $this->avisar()->assertOk();
        $this->avisar()->assertOk();

        $this->assertSame(1, Pago::count());
        $this->assertSame('pending', $pedido->fresh()->estado);
        // Y al negocio se le avisa una sola vez, no tres.
        Mail::assertSentCount(1);
    }

    /** El pedido queda con saldo cero: es lo que ve el negocio en la cuenta del cliente. */
    public function test_el_pedido_acreditado_queda_sin_saldo(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago(7500);
        $this->mercadoPagoResponde('approved', 7500, (string) $pedido->id);

        $this->avisar()->assertOk();

        $this->assertEqualsWithDelta(0.0, $pedido->fresh()->saldo, 0.001);
    }

    /** No necesita sesión ni token: la manda un servidor, no un navegador. */
    public function test_el_webhook_no_exige_token_de_formulario(): void
    {
        $this->conCobroOnline();
        $pedido = $this->pedidoEsperandoPago();
        $this->mercadoPagoResponde('approved', 3000, (string) $pedido->id);

        // Sin sesión iniciada y sin token CSRF: si la ruta no estuviera exenta,
        // esto daría 419 en vez de 200.
        $this->avisar()->assertOk();
    }

    /**
     * Si MercadoPago no contesta, el webhook tiene que fallar fuerte para que
     * ellos reintenten. Tragarse el error y contestar 200 perdería el aviso
     * para siempre, y con él la acreditación del pedido.
     */
    public function test_si_no_se_puede_consultar_el_pago_el_webhook_falla_para_que_reintenten(): void
    {
        $this->conCobroOnline();
        $this->pedidoEsperandoPago();

        $this->instance(ConsultarPago::class, new class extends ConsultarPago
        {
            public function porId(string $paymentId): array
            {
                throw new RuntimeException('MercadoPago no contesta');
            }
        });

        $this->withoutExceptionHandling()->expectException(RuntimeException::class);

        $this->avisar();
    }
}
