<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecioMayoristaTest extends TestCase
{
    use RefreshDatabase;

    private function presentacion(array $extra = []): Presentacion
    {
        return Presentacion::factory()->create($extra + ['precio' => 1000, 'stock' => 100]);
    }

    private function negocio(): User
    {
        return User::factory()->create(['tipo_cliente' => 'negocio']);
    }

    private function particular(): User
    {
        return User::factory()->create(['tipo_cliente' => 'particular']);
    }

    public function test_el_negocio_paga_por_mayor_y_el_particular_no(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800]);

        $this->assertSame(800.0, $p->precioPara($this->negocio()));
        $this->assertSame(1000.0, $p->precioPara($this->particular()));
        $this->assertSame(1000.0, $p->precioPara(null));
    }

    public function test_sin_precio_mayorista_cargado_todos_pagan_igual(): void
    {
        $p = $this->presentacion();

        $this->assertSame(1000.0, $p->precioPara($this->negocio(), 50));
    }

    public function test_cualquiera_paga_por_mayor_al_llegar_a_la_cantidad(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800, 'cantidad_mayorista' => 6]);

        $this->assertSame(1000.0, $p->precioPara($this->particular(), 5));
        $this->assertSame(800.0, $p->precioPara($this->particular(), 6));
        $this->assertSame(800.0, $p->precioPara($this->particular(), 12));
    }

    /**
     * Los dos descuentos no se encadenan: si se sumaran, una oferta del 30%
     * sobre un mayorista ya rebajado terminaría vendiendo por debajo del costo.
     */
    public function test_la_oferta_y_el_mayorista_no_se_suman_gana_el_menor(): void
    {
        $ofertaGana = $this->presentacion(['precio_mayorista' => 800, 'oferta_precio' => 700]);
        $mayoristaGana = $this->presentacion(['precio_mayorista' => 600, 'oferta_precio' => 700]);

        $this->assertSame(700.0, $ofertaGana->precioPara($this->negocio()));
        $this->assertSame(600.0, $mayoristaGana->precioPara($this->negocio()));
    }

    public function test_el_carrito_del_negocio_usa_el_precio_por_mayor(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800]);

        $this->actingAs($this->negocio())
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->get('/carrito')
            // Sin decimales, los precios viajan como enteros en el JSON.
            ->assertInertia(fn ($page) => $page
                ->where('items.0.precio', 800)
                ->where('items.0.subtotal', 1600));
    }

    public function test_el_carrito_aplica_el_precio_por_cantidad_al_llegar(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800, 'cantidad_mayorista' => 6]);
        $cliente = $this->particular();

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 6]])
            ->get('/carrito')
            ->assertInertia(fn ($page) => $page->where('items.0.precio', 800));

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 5]])
            ->get('/carrito')
            ->assertInertia(fn ($page) => $page
                ->where('items.0.precio', 1000)
                // Y se le avisa que llevando una más le conviene.
                ->where('items.0.mayorista_desde.cantidad', 6));
    }

    public function test_el_pedido_queda_guardado_con_el_precio_por_mayor(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800]);

        $this->actingAs($this->negocio())
            ->withSession(['cart' => [(string) $p->id => 3]])
            ->post('/checkout', ['entrega' => 'retiro']);

        $this->assertDatabaseHas('pedido_items', [
            'presentacion_id' => $p->id,
            'cantidad' => 3,
            'precio_unitario' => 800.00,
            'subtotal' => 2400.00,
        ]);
    }

    public function test_el_particular_no_ve_el_precio_mayorista_en_la_tienda(): void
    {
        $p = $this->presentacion(['precio_mayorista' => 800]);

        $datos = $this->actingAs($this->particular())->get('/')->baseResponse->content();

        $this->assertStringNotContainsString('precio_mayorista', $datos);
        $this->assertSame('1000.00', $p->fresh()->toArray()['precio']);
    }
}
