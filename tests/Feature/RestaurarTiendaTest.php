<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\SeccionMenu;
use App\Models\User;
use App\Services\RestaurarTienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurarTiendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_deja_la_configuracion_y_el_menu_como_de_fabrica(): void
    {
        Configuracion::actual()->update([
            'nombre_negocio' => 'Origen Dorado',
            'color_acento' => '#ebb52a',
            'whatsapp' => '5492477504048',
            'mostrar_combos' => false,
        ]);
        SeccionMenu::query()->delete();
        SeccionMenu::create(['titulo' => 'Inventada', 'destino_tipo' => 'ofertas']);

        (new RestaurarTienda)->ejecutar();

        $config = Configuracion::actual()->fresh();
        $this->assertSame('Mi Tienda', $config->nombre_negocio);
        $this->assertNull($config->color_acento);
        $this->assertNull($config->whatsapp);
        $this->assertTrue($config->mostrar_combos);

        $this->assertSame(
            ['Inicio', 'Categorías', 'Marcas', 'Combos', 'Nuevos', 'Ofertas'],
            SeccionMenu::activos()->pluck('titulo')->all(),
        );
    }

    public function test_vacia_el_catalogo(): void
    {
        Presentacion::factory()->create();

        (new RestaurarTienda)->ejecutar();

        $this->assertSame(0, Producto::withTrashed()->count());
        $this->assertSame(0, Presentacion::withTrashed()->count());
        $this->assertSame(0, Marca::withTrashed()->count());
        $this->assertSame(0, Categoria::withTrashed()->count());
    }

    /**
     * Lo más importante del reset: no puede llevarse puesto el historial del
     * negocio. Con pedidos existentes el catálogo se archiva en vez de
     * borrarse, porque los pedidos apuntan a las presentaciones.
     */
    public function test_no_toca_los_pedidos_ni_a_los_clientes(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);
        $presentacion = Presentacion::factory()->create(['precio' => 1000, 'stock' => 10]);
        $pedido = Pedido::create(['user_id' => $cliente->id, 'estado' => 'pending', 'total' => 1000, 'datos_cliente' => []]);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'subtotal' => 1000,
        ]);

        (new RestaurarTienda)->ejecutar();

        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id]);
        $this->assertDatabaseHas('pedido_items', ['pedido_id' => $pedido->id]);
        $this->assertDatabaseHas('users', ['id' => $cliente->id]);

        // El producto ya no se ve en la tienda, pero el pedido lo sigue encontrando.
        $this->assertSame(0, Producto::count());
        $this->assertNotNull($pedido->items()->first()->presentacion()->withTrashed()->first());
    }

    public function test_solo_el_admin_ve_el_boton(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operador']));
        $this->assertFalse(\App\Filament\Pages\Configuracion::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertTrue(\App\Filament\Pages\Configuracion::canAccess());
    }
}
