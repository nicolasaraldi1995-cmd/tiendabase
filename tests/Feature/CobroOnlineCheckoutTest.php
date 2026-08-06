<?php

namespace Tests\Feature;

use App\Mail\PedidoNuevoMail;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\User;
use App\Services\MercadoPago\CrearPreferencia;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * El checkout con cobro online. Ningún test de acá le pega a MercadoPago: el
 * servicio que habla con ellos se reemplaza por uno de mentira, porque lo que
 * se prueba es la decisión (a quién se manda a pagar y en qué estado queda el
 * pedido), no su API.
 */
class CobroOnlineCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const URL_DE_PAGO = 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=fake';

    private function presentacion(): Presentacion
    {
        return Presentacion::factory()->create(['precio' => 1500, 'stock' => 40, 'activo' => true]);
    }

    private function cliente(): User
    {
        return User::factory()->create(['role' => 'cliente', 'tipo_cliente' => 'particular']);
    }

    private function conCobroOnline(string $modo = 'online_opcional'): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => $modo,
            'mp_access_token' => 'TEST-token-de-prueba',
            'mp_webhook_secret' => 'secreto',
            'email_avisos' => 'dueno@negocio.test',
        ]);
    }

    /** Devuelve una dirección de pago sin salir a la red. */
    private function preferenciaFalsa(): void
    {
        $this->instance(CrearPreferencia::class, new class extends CrearPreferencia
        {
            public function paraElPedido(Pedido $pedido): string
            {
                return CobroOnlineCheckoutTest::urlDePago();
            }
        });
    }

    public static function urlDePago(): string
    {
        return self::URL_DE_PAGO;
    }

    /** El que paga online no entra al circuito del negocio hasta que la plata esté. */
    public function test_el_que_paga_online_queda_esperando_el_pago_y_se_lo_manda_a_mercadopago(): void
    {
        Mail::fake();
        $this->conCobroOnline();
        $this->preferenciaFalsa();
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'online'])
            ->assertRedirect(self::URL_DE_PAGO);

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();

        $this->assertSame('awaiting_payment', $pedido->estado);
        // El stock se reserva igual: si no, dos clientes podrían estar pagando
        // la última unidad al mismo tiempo.
        $this->assertSame(38, $p->fresh()->stock);
        // Y al negocio no se le avisa todavía: prepararía mercadería por un
        // pedido que nadie pagó.
        Mail::assertNotSent(PedidoNuevoMail::class);
    }

    public function test_el_que_elige_coordinar_sigue_el_camino_de_siempre(): void
    {
        Mail::fake();
        $this->conCobroOnline();
        $this->preferenciaFalsa();
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'coordinar']);

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();

        $this->assertSame('pending', $pedido->estado);
        Mail::assertSent(PedidoNuevoMail::class);
    }

    /**
     * Con el cobro obligatorio no importa lo que mande el formulario: eso se
     * puede escribir a mano desde afuera del navegador.
     */
    public function test_con_cobro_obligatorio_no_se_puede_esquivar_el_pago(): void
    {
        $this->conCobroOnline('online_obligatorio');
        $this->preferenciaFalsa();
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'coordinar'])
            ->assertRedirect(self::URL_DE_PAGO);

        $this->assertSame('awaiting_payment', Pedido::where('user_id', $cliente->id)->value('estado'));
    }

    /**
     * Si el negocio dice que cobra online pero no cargó credenciales, el
     * cliente igual tiene que poder comprar. Mandar "online" desde afuera no
     * cambia nada.
     */
    public function test_sin_credenciales_el_pedido_sigue_el_camino_de_siempre(): void
    {
        Configuracion::actual()->update(['modo_cobro' => 'online_obligatorio']);
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'online'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', Pedido::where('user_id', $cliente->id)->value('estado'));
    }

    /**
     * MercadoPago caído no puede costarle una venta al negocio: el pedido ya
     * existe y ya reservó stock, así que vuelve al circuito normal en vez de
     * quedar colgado esperando un pago que nunca se va a poder hacer.
     */
    public function test_si_mercadopago_falla_el_pedido_no_se_pierde(): void
    {
        Mail::fake();
        $this->conCobroOnline();
        $this->instance(CrearPreferencia::class, new class extends CrearPreferencia
        {
            public function paraElPedido(Pedido $pedido): string
            {
                throw new RuntimeException('MercadoPago no contesta');
            }
        });

        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 3]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'online'])
            ->assertSessionHas('aviso');

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();

        $this->assertSame('pending', $pedido->estado);
        $this->assertSame(37, $p->fresh()->stock);
        // Y ahora sí se le avisa al negocio, porque el pedido es de los que se
        // coordinan a mano.
        Mail::assertSent(PedidoNuevoMail::class);
    }

    public function test_si_el_cliente_elige_hay_que_mandar_una_forma_de_pago_valida(): void
    {
        $this->conCobroOnline();
        $p = $this->presentacion();

        $this->actingAs($this->cliente())
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'con-monedas'])
            ->assertSessionHasErrors('forma_pago');
    }

    /**
     * El test que cuida la plata: lo que se le manda a cobrar a MercadoPago
     * tiene que sumar exactamente el total del pedido. Si esto se desalinea, el
     * cliente ve un número en la tienda y paga otro.
     */
    public function test_lo_que_se_manda_a_cobrar_suma_el_total_del_pedido(): void
    {
        $this->conCobroOnline();
        $caro = Presentacion::factory()->create(['precio' => 1234.56, 'stock' => 10, 'activo' => true]);
        $barato = Presentacion::factory()->create(['precio' => 99.99, 'stock' => 10, 'activo' => true]);
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $caro->id => 3, (string) $barato->id => 7]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'coordinar']);

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();
        $cuerpo = app(CrearPreferencia::class)->cuerpoParaElPedido($pedido);

        $suma = collect($cuerpo['items'])->sum(fn ($i) => $i['unit_price'] * $i['quantity']);

        $this->assertEqualsWithDelta((float) $pedido->total, $suma, 0.001);
        $this->assertSame((string) $pedido->id, $cuerpo['external_reference']);
        $this->assertCount(2, $cuerpo['items']);

        foreach ($cuerpo['items'] as $item) {
            $this->assertSame('ARS', $item['currency_id']);
            $this->assertGreaterThan(0, $item['unit_price']);
            $this->assertGreaterThan(0, $item['quantity']);
        }
    }

    /** La preferencia vence junto con la reserva de stock, no después. */
    public function test_la_preferencia_vence_con_la_reserva_de_stock(): void
    {
        $this->conCobroOnline();
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro', 'forma_pago' => 'coordinar']);

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();
        $cuerpo = app(CrearPreferencia::class)->cuerpoParaElPedido($pedido);

        $this->assertTrue($cuerpo['expires']);
        $this->assertEqualsWithDelta(
            now()->addMinutes(Pedido::MINUTOS_PARA_PAGAR)->timestamp,
            Carbon::parse($cuerpo['expiration_date_to'])->timestamp,
            5,
        );
    }

    /**
     * Que el backend sepa cobrar no sirve si la pantalla no se entera: sin este
     * dato, el cliente nunca ve la opción de pagar y la función queda apagada
     * en silencio. Las 4 plantillas comparten este checkout, así que alcanza
     * con probarlo una vez.
     */
    public function test_la_pantalla_del_checkout_se_entera_de_que_se_puede_pagar_online(): void
    {
        $p = $this->presentacion();
        $cliente = $this->cliente();

        // Apagado: la pantalla no ofrece nada.
        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->get('/checkout')
            ->assertInertia(fn ($page) => $page
                ->where('pagoOnline.disponible', false)
                ->where('pagoOnline.obligatorio', false));

        $this->conCobroOnline('online_obligatorio');

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->get('/checkout')
            ->assertInertia(fn ($page) => $page
                ->where('pagoOnline.disponible', true)
                ->where('pagoOnline.obligatorio', true));
    }

    /** Sin credenciales cargadas el servicio avisa en vez de intentar cobrar. */
    public function test_el_servicio_no_intenta_cobrar_sin_credenciales(): void
    {
        $pedido = Pedido::factory()->create(['estado' => 'awaiting_payment']);

        $this->expectException(RuntimeException::class);

        app(CrearPreferencia::class)->paraElPedido($pedido);
    }
}
