<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ConectarMercadoPago;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La guía para conectar MercadoPago.
 *
 * Lo que cuidan estos tests: que la pantalla RENDERICE (una guía rota es peor
 * que ninguna, porque aparece justo cuando alguien está perdido) y que muestre
 * la dirección de ESTA tienda y no un ejemplo — que es el dato donde más fácil
 * se equivoca uno al copiarlo.
 */
class ConectarMercadoPagoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_la_guia_se_puede_abrir(): void
    {
        $this->actingAs($this->admin())
            ->get(ConectarMercadoPago::getUrl())
            ->assertOk();
    }

    /**
     * El dato que más se copia mal. Si mostrara un ejemplo genérico, el dueño
     * pegaría esa dirección en MercadoPago y los pagos no se acreditarían nunca.
     */
    public function test_muestra_la_direccion_de_esta_tienda_lista_para_copiar(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ConectarMercadoPago::class)
            ->assertSee(url('/webhooks/mercadopago'));
    }

    /** Con la tienda recién instalada, nada figura como resuelto. */
    public function test_una_tienda_sin_configurar_no_muestra_nada_como_listo(): void
    {
        $pagina = Livewire::actingAs($this->admin())->test(ConectarMercadoPago::class);

        $this->assertFalse($pagina->instance()->todoListo());
        $this->assertSame(
            ['modo' => false, 'token' => false, 'secreto' => false],
            $pagina->instance()->loQueYaEsta(),
        );
    }

    public function test_con_todo_cargado_la_guia_lo_reconoce(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'APP_USR-token',
            'mp_webhook_secret' => 'secreto',
        ]);

        $pagina = Livewire::actingAs($this->admin())->test(ConectarMercadoPago::class);

        $this->assertTrue($pagina->instance()->todoListo());
        $this->assertSame(
            ['modo' => true, 'token' => true, 'secreto' => true],
            $pagina->instance()->loQueYaEsta(),
        );
        $this->assertFalse($pagina->instance()->enModoPrueba());
    }

    /**
     * Que se avise el modo prueba importa: es un error que no se nota hasta que
     * falta la plata de la primera venta real.
     */
    public function test_avisa_cuando_las_credenciales_son_de_prueba(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'TEST-1234',
            'mp_webhook_secret' => 'secreto',
        ]);

        $pagina = Livewire::actingAs($this->admin())->test(ConectarMercadoPago::class);

        $this->assertTrue($pagina->instance()->enModoPrueba());
        $pagina->assertSee('no entra plata de verdad');
    }

    /** El operador no toca la plata del negocio: la guía no es para él. */
    public function test_el_operador_no_entra(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->get(ConectarMercadoPago::getUrl())
            ->assertForbidden();
    }
}
