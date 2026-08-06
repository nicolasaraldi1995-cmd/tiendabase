<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los cimientos del cobro online. El test más importante de acá no es ninguno
 * de los que prueban lo nuevo: es el primero, que prueba que NO cambió nada.
 * Todo lo que se agregue después de esta fase se apoya en que la tienda sigue
 * funcionando igual mientras el negocio no active nada.
 */
class CobroOnlineCimientosTest extends TestCase
{
    use RefreshDatabase;

    private function presentacion(): Presentacion
    {
        return Presentacion::factory()->create(['precio' => 1000, 'stock' => 50, 'activo' => true]);
    }

    private function cliente(): User
    {
        return User::factory()->create(['role' => 'cliente', 'tipo_cliente' => 'particular']);
    }

    /**
     * La regresión que hay que cuidar: con el cobro online sin activar, el
     * checkout tiene que hacer exactamente lo de siempre — pedido en 'pending',
     * carrito vacío y pantalla de confirmación.
     */
    public function test_sin_cobro_online_el_checkout_se_comporta_igual_que_siempre(): void
    {
        $p = $this->presentacion();
        $cliente = $this->cliente();

        $this->assertSame('coordinar', Configuracion::actual()->modoCobro());

        $respuesta = $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro']);

        $pedido = Pedido::where('user_id', $cliente->id)->firstOrFail();

        $respuesta->assertRedirect(route('checkout.confirmacion', $pedido->id));
        $this->assertSame('pending', $pedido->estado);
        $this->assertSame('2000.00', $pedido->total);
        $this->assertEmpty(session('cart', []));
        // Y el stock se reservó, como siempre.
        $this->assertSame(48, $p->fresh()->stock);
    }

    public function test_una_tienda_recien_instalada_no_cobra_online(): void
    {
        $config = Configuracion::actual();

        $this->assertSame('coordinar', $config->modoCobro());
        $this->assertFalse($config->cobraOnline());
        $this->assertFalse($config->puedeCobrarOnline());
        $this->assertFalse($config->exigeCobroOnline());
    }

    /**
     * Un valor que no existe en MODOS_DE_COBRO cae en 'coordinar'. El default
     * seguro es que el negocio cobre como siempre: mandar clientes a pagar
     * online por un dato roto sería mucho peor que no cobrar online.
     */
    public function test_un_modo_de_cobro_desconocido_cae_en_coordinar(): void
    {
        Configuracion::actual()->update(['modo_cobro' => 'lo-que-sea']);

        $this->assertSame('coordinar', Configuracion::actual()->modoCobro());
        $this->assertFalse(Configuracion::actual()->cobraOnline());
    }

    /**
     * Querer cobrar online no alcanza: sin token no hay a dónde mandar al
     * cliente. Si esto devolviera true, el checkout intentaría crear un cobro
     * imposible y el cliente se quedaría sin poder comprar.
     */
    public function test_querer_cobrar_online_sin_credenciales_no_alcanza(): void
    {
        Configuracion::actual()->update(['modo_cobro' => 'online_obligatorio']);
        $config = Configuracion::actual();

        $this->assertTrue($config->cobraOnline());
        $this->assertFalse($config->puedeCobrarOnline());
        $this->assertFalse($config->exigeCobroOnline());
    }

    public function test_con_credenciales_cargadas_si_puede_cobrar_online(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_obligatorio',
            'mp_access_token' => 'APP_USR-un-token-de-verdad',
            'mp_webhook_secret' => 'un-secreto',
        ]);
        $config = Configuracion::actual();

        $this->assertTrue($config->puedeCobrarOnline());
        $this->assertTrue($config->exigeCobroOnline());
        $this->assertSame('APP_USR-un-token-de-verdad', $config->tokenMercadoPago());
        $this->assertSame('un-secreto', $config->secretoWebhookMercadoPago());
        $this->assertFalse($config->cobroOnlineEnPrueba());
    }

    /** Para que nadie crea que ya está cobrando de verdad cuando no. */
    public function test_las_credenciales_de_prueba_se_reconocen(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'TEST-1234567890',
        ]);

        $this->assertTrue(Configuracion::actual()->cobroOnlineEnPrueba());
    }

    /**
     * El caso que más duele: se regeneró la APP_KEY (pasa al correr
     * `composer run setup` sobre una tienda ya instalada) y las credenciales
     * guardadas quedan indescifrables. Sin el catch, CUALQUIER pantalla que
     * toque la configuración —o sea todas— tiraría error 500. Con el catch, lo
     * único que pasa es que el cobro online se apaga.
     */
    public function test_una_credencial_indescifrable_apaga_el_cobro_pero_no_rompe_la_tienda(): void
    {
        Configuracion::actual()->update(['modo_cobro' => 'online_opcional']);

        // Escrito en crudo, salteando el cast: es exactamente lo que queda en
        // la base cuando la clave con la que se encriptó ya no es la vigente.
        DB::table('configuraciones')->where('id', 1)->update([
            'mp_access_token' => 'esto-no-se-puede-descifrar',
        ]);

        $config = Configuracion::actual()->fresh();

        $this->assertNull($config->tokenMercadoPago());
        $this->assertFalse($config->puedeCobrarOnline());

        // Y la tienda sigue de pie.
        $this->get('/')->assertOk();
    }

    /**
     * El índice único es lo único que impide cobrar dos veces el mismo pago.
     * MercadoPago reintenta la misma notificación por diseño, así que esto no
     * es un caso raro: es el caso normal.
     */
    public function test_no_se_puede_registrar_dos_veces_el_mismo_pago_de_mercadopago(): void
    {
        $cliente = $this->cliente();

        Pago::create([
            'user_id' => $cliente->id,
            'monto' => 1000,
            'metodo' => 'mercadopago',
            'fecha' => now(),
            'mp_payment_id' => '99887766',
        ]);

        $this->expectException(QueryException::class);

        Pago::create([
            'user_id' => $cliente->id,
            'monto' => 1000,
            'metodo' => 'mercadopago',
            'fecha' => now(),
            'mp_payment_id' => '99887766',
        ]);
    }

    /** Los pagos que carga el negocio a mano no tienen id de MercadoPago, y son varios. */
    public function test_los_pagos_cargados_a_mano_conviven_sin_id_de_mercadopago(): void
    {
        $cliente = $this->cliente();

        foreach ([500, 800, 1200] as $monto) {
            Pago::create([
                'user_id' => $cliente->id,
                'monto' => $monto,
                'metodo' => 'efectivo',
                'fecha' => now(),
            ]);
        }

        $this->assertSame(3, Pago::where('user_id', $cliente->id)->count());
    }

    /**
     * 'awaiting_payment' existe para mostrarse y filtrarse, pero el negocio no
     * lo puede poner a mano: sin una preferencia de pago viva detrás, ese
     * pedido esperaría un pago que nadie pidió hasta que lo cancele el
     * vencimiento.
     */
    public function test_esperando_pago_se_muestra_pero_no_se_elige_a_mano(): void
    {
        $this->assertArrayHasKey('awaiting_payment', Pedido::ESTADOS);
        $this->assertArrayNotHasKey('awaiting_payment', Pedido::estadosQueSeEligen());

        // Y los que sí se eligen siguen estando todos.
        $this->assertArrayHasKey('pending', Pedido::estadosQueSeEligen());
        $this->assertArrayHasKey('canceled', Pedido::estadosQueSeEligen());
        $this->assertCount(count(Pedido::ESTADOS) - 1, Pedido::estadosQueSeEligen());
    }

    /** Un pedido esperando pago está bloqueado: ni el cliente ni el panel lo tocan. */
    public function test_un_pedido_esperando_pago_no_es_editable(): void
    {
        $pedido = Pedido::factory()->create(['estado' => 'awaiting_payment']);

        $this->assertTrue($pedido->esperaPago());
        $this->assertFalse($pedido->esEditable());
    }
}
