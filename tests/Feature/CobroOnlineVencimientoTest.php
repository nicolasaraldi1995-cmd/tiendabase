<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\User;
use App\Services\MercadoPago\ConsultarPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * El cierre de los pedidos que quedaron esperando un pago.
 *
 * El pedido que se manda a pagar reserva stock desde el minuto cero, así que
 * si el cliente abandona hay que soltarlo. Pero antes de cancelar hay que
 * preguntar: un aviso perdido haría llegar acá como impago un pedido que en
 * realidad se cobró, y cancelar una venta cobrada es el peor error posible de
 * todo este circuito.
 */
class CobroOnlineVencimientoTest extends TestCase
{
    use RefreshDatabase;

    private Presentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'TEST-token',
            'mp_webhook_secret' => 'secreto',
        ]);

        $this->presentacion = Presentacion::factory()->create([
            'precio' => 2500, 'stock' => 20, 'activo' => true,
        ]);
    }

    /** Un pedido esperando pago, con su stock ya reservado por el observer. */
    private function pedidoEsperandoPago(int $minutosDeAntiguedad, int $cantidad = 4): Pedido
    {
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'awaiting_payment',
            'total' => 2500 * $cantidad,
        ]);

        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $this->presentacion->id,
            'cantidad' => $cantidad,
            'precio_unitario' => 2500,
            'subtotal' => 2500 * $cantidad,
        ]);

        // Después de crear el ítem, para que el observer ya haya corrido.
        $pedido->forceFill(['created_at' => now()->subMinutes($minutosDeAntiguedad)])->save();

        return $pedido->fresh();
    }

    private function mercadoPagoDice(?array $pago, bool $falla = false): void
    {
        $this->instance(ConsultarPago::class, new class($pago, $falla) extends ConsultarPago
        {
            public function __construct(private ?array $pago, private bool $falla) {}

            public function aprobadoDelPedido(int $pedidoId): ?array
            {
                if ($this->falla) {
                    throw new RuntimeException('MercadoPago no contesta');
                }

                return $this->pago;
            }
        });
    }

    private function vencer(): void
    {
        $this->artisan('pedidos:vencer-impagos')->assertSuccessful();
    }

    public function test_un_pedido_abandonado_se_cancela_y_devuelve_el_stock(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 45);

        // El observer ya reservó las 4 unidades al crear el ítem.
        $this->assertSame(16, $this->presentacion->fresh()->stock);

        $this->mercadoPagoDice(null);
        $this->vencer();

        $this->assertSame('canceled', $pedido->fresh()->estado);
        $this->assertSame(20, $this->presentacion->fresh()->stock, 'El stock no volvió al catálogo.');
    }

    /**
     * EL test de esta fase. Si el aviso de MercadoPago se perdió —servidor
     * dormido, corte de red, reintentos agotados—, este pedido llega acá con
     * cara de impago aunque el cliente ya haya pagado. Cancelarlo sería
     * quedarse con la plata y devolverle el stock a otro.
     */
    public function test_un_pedido_vencido_que_en_realidad_se_pago_se_acredita_en_vez_de_cancelarse(): void
    {
        Mail::fake();
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 45);

        $this->mercadoPagoDice([
            'id' => '778899',
            'estado' => 'approved',
            'monto' => 10000.0,
            'pedido_id' => (string) $pedido->id,
        ]);

        $this->vencer();

        $this->assertSame('pending', $pedido->fresh()->estado);
        $this->assertSame(1, Pago::where('mp_payment_id', '778899')->count());
        // Y el stock NO se devolvió: la mercadería es del cliente que pagó.
        $this->assertSame(16, $this->presentacion->fresh()->stock);
    }

    /**
     * Si no se puede preguntar, no se cancela. Vale mucho más reintentar en
     * cinco minutos que cancelar a ciegas un pedido que quizá está pago.
     */
    public function test_si_no_se_puede_consultar_no_se_cancela_nada(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 45);

        $this->mercadoPagoDice(null, falla: true);
        $this->vencer();

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
        $this->assertSame(16, $this->presentacion->fresh()->stock);
    }

    /** El que todavía está dentro de la ventana sigue esperando tranquilo. */
    public function test_un_pedido_reciente_no_se_toca(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 5);

        $this->mercadoPagoDice(null);
        $this->vencer();

        $this->assertSame('awaiting_payment', $pedido->fresh()->estado);
        $this->assertSame(16, $this->presentacion->fresh()->stock);
    }

    /** Los pedidos que coordinan el pago no vencen: no están esperando nada. */
    public function test_los_pedidos_que_no_esperan_pago_no_se_tocan(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 300);
        $pedido->update(['estado' => 'pending']);

        $this->mercadoPagoDice(null);
        $this->vencer();

        $this->assertSame('pending', $pedido->fresh()->estado);
        $this->assertSame(16, $this->presentacion->fresh()->stock);
    }

    /** Un pedido que falla no puede dejar a los demás con el stock trabado. */
    public function test_un_pedido_problematico_no_frena_a_los_demas(): void
    {
        $roto = $this->pedidoEsperandoPago(minutosDeAntiguedad: 45, cantidad: 2);
        $sano = $this->pedidoEsperandoPago(minutosDeAntiguedad: 45, cantidad: 3);

        $this->instance(ConsultarPago::class, new class($roto->id) extends ConsultarPago
        {
            public function __construct(private int $idQueFalla) {}

            public function aprobadoDelPedido(int $pedidoId): ?array
            {
                if ($pedidoId === $this->idQueFalla) {
                    throw new RuntimeException('este explota');
                }

                return null;
            }
        });

        $this->vencer();

        $this->assertSame('awaiting_payment', $roto->fresh()->estado);
        $this->assertSame('canceled', $sano->fresh()->estado);
        // Volvieron las 3 del sano; las 2 del roto siguen reservadas.
        $this->assertSame(18, $this->presentacion->fresh()->stock);
    }

    // ─── La pantalla de vuelta ────────────────────────────────────────────

    /**
     * El cliente vuelve de MercadoPago y el aviso todavía no llegó. La pantalla
     * pregunta en vez de creerle a la URL: si no lo hiciera, vería "esperando
     * pago" habiendo pagado, y encima esa dirección la puede escribir cualquiera
     * a mano.
     */
    public function test_al_volver_de_pagar_la_pantalla_consulta_el_estado_real(): void
    {
        Mail::fake();
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 1);

        $this->mercadoPagoDice([
            'id' => '445566',
            'estado' => 'approved',
            'monto' => 10000.0,
            'pedido_id' => (string) $pedido->id,
        ]);

        $this->actingAs($pedido->user)
            ->get(route('checkout.confirmacion', $pedido->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pedido.estado', 'pending'));

        $this->assertSame(1, Pago::count());
    }

    /** Sin pago acreditado, la pantalla lo dice tal cual: no inventa un éxito. */
    public function test_sin_pago_acreditado_la_pantalla_sigue_diciendo_que_espera(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 1);

        $this->mercadoPagoDice(null);

        $this->actingAs($pedido->user)
            ->get(route('checkout.confirmacion', $pedido->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pedido.estado', 'awaiting_payment'));
    }

    /** Que MercadoPago no conteste no puede dejar al cliente sin ver su pedido. */
    public function test_si_mercadopago_no_contesta_la_pantalla_igual_se_muestra(): void
    {
        $pedido = $this->pedidoEsperandoPago(minutosDeAntiguedad: 1);

        $this->mercadoPagoDice(null, falla: true);

        $this->actingAs($pedido->user)
            ->get(route('checkout.confirmacion', $pedido->id))
            ->assertOk();
    }
}
