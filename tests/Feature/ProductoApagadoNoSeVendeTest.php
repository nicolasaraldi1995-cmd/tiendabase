<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apagar un producto en el panel lo sacaba de los listados y del buscador, pero
 * seguía comprándose por link directo: el filtro de "activo" miraba solo la
 * presentación, y el carrito apenas comprobaba que el producto existiera. El
 * dueño creía haberlo sacado de la venta y se lo seguían pidiendo.
 */
class ProductoApagadoNoSeVendeTest extends TestCase
{
    use RefreshDatabase;

    private function producto(bool $activo): Producto
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Miel discontinuada',
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => $activo,
        ]);

        // La presentación queda ACTIVA a propósito: es el caso que se escapaba.
        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 5000,
            'stock' => 10,
            'activo' => true,
        ]);

        return $producto;
    }

    public function test_no_se_puede_agregar_al_carrito(): void
    {
        $presentacion = $this->producto(activo: false)->presentaciones()->first();

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1])
            ->assertSessionHasErrors('presentacion_id');

        $this->assertSame([], session('cart', []));
    }

    public function test_su_ficha_deja_de_existir(): void
    {
        $producto = $this->producto(activo: false);

        $this->get("/productos/{$producto->slug}")->assertNotFound();
    }

    public function test_si_ya_estaba_en_un_carrito_no_llega_al_checkout(): void
    {
        $producto = $this->producto(activo: true);
        $presentacion = $producto->presentaciones()->first();

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 2]);

        // El negocio lo da de baja con el carrito ya armado.
        $producto->update(['activo' => false]);

        $this->get('/carrito')->assertOk()->assertDontSee('Miel discontinuada');
    }

    public function test_el_que_sigue_activo_se_vende_igual(): void
    {
        $producto = $this->producto(activo: true);
        $presentacion = $producto->presentaciones()->first();

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1])
            ->assertSessionHasNoErrors();

        $this->get("/productos/{$producto->slug}")->assertOk();
    }
}
