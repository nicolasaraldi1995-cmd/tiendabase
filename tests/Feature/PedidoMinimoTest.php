<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoMinimoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Configuracion::actual()->update(['pedido_minimo_mayorista' => 50000]);
    }

    public function test_el_negocio_no_puede_confirmar_por_debajo_del_minimo(): void
    {
        $p = Presentacion::factory()->create(['precio' => 1000, 'stock' => 100]);

        $this->actingAs(User::factory()->create(['tipo_cliente' => 'negocio']))
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertSessionHasErrors('total');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_llegando_al_minimo_el_pedido_entra(): void
    {
        $p = Presentacion::factory()->create(['precio' => 1000, 'stock' => 100]);

        $this->actingAs(User::factory()->create(['tipo_cliente' => 'negocio']))
            ->withSession(['cart' => [(string) $p->id => 50]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pedidos', 1);
    }

    /**
     * El mínimo es para los que compran para revender: al particular que se
     * lleva una unidad no se le puede exigir el piso mayorista.
     */
    public function test_al_particular_no_se_le_exige_el_minimo(): void
    {
        $p = Presentacion::factory()->create(['precio' => 1000, 'stock' => 100]);

        $this->actingAs(User::factory()->create(['tipo_cliente' => 'particular']))
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_sin_minimo_configurado_nadie_queda_frenado(): void
    {
        Configuracion::actual()->update(['pedido_minimo_mayorista' => 0]);
        $p = Presentacion::factory()->create(['precio' => 1000, 'stock' => 100]);

        $this->actingAs(User::factory()->create(['tipo_cliente' => 'negocio']))
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pedidos', 1);
    }
}
