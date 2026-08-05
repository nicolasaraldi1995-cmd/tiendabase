<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dos cosas que se destaparon probando la tienda como cliente: el tope de
 * unidades se podía pasar sumando de a poco (y por encima de cierto punto el
 * subtotal no entra en la columna y el cliente ve una pantalla de error), y el
 * checkout prometía envío gratis aunque la promo estuviera apagada.
 */
class TopesYEnvioGratisTest extends TestCase
{
    use RefreshDatabase;

    private function presentacion(): Presentacion
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);

        return Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 7500,
            'stock' => 10,
            'activo' => true,
        ]);
    }

    private function cliente(): User
    {
        // Con el control apagado nada más acota la cantidad: es el escenario
        // donde el tope es lo único que queda en pie.
        Configuracion::actual()->update(['controlar_stock' => false]);

        return User::factory()->create(['role' => 'cliente']);
    }

    public function test_el_tope_del_carrito_no_se_pasa_sumando_de_a_poco(): void
    {
        $presentacion = $this->presentacion();
        $tope = Presentacion::MAXIMO_POR_PEDIDO;

        $this->actingAs($this->cliente());

        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => $tope])
            ->assertSessionHasNoErrors();

        // La segunda vez el pedido acumulado se pasa: antes entraba igual y el
        // carrito quedaba en el doble del tope.
        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => $tope])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame($tope, session('cart')[(string) $presentacion->id]);
    }

    public function test_el_tope_tambien_rige_al_editar_un_pedido(): void
    {
        $presentacion = $this->presentacion();
        $cliente = $this->cliente();
        $tope = Presentacion::MAXIMO_POR_PEDIDO;

        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending']);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => $tope,
            'precio_unitario' => 7500,
            'subtotal' => 7500 * $tope,
        ]);

        $this->actingAs($cliente);

        // Sin tope, sumar de a poco llegaba a un subtotal que no entra en la
        // columna: la respuesta era un error 500 en la cara del cliente.
        $this->post("/mis-pedidos/{$pedido->id}/item", ['presentacion_id' => $presentacion->id, 'cantidad' => 5000])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame($tope, $pedido->items()->first()->cantidad);
    }

    public function test_una_cantidad_disparatada_avisa_en_vez_de_reventar(): void
    {
        $presentacion = $this->presentacion();
        $cliente = $this->cliente();

        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending']);

        $this->actingAs($cliente)
            ->post("/mis-pedidos/{$pedido->id}/item", ['presentacion_id' => $presentacion->id, 'cantidad' => 999999])
            ->assertSessionHasErrors('cantidad');
    }

    public function test_en_cero_la_promo_de_envio_esta_apagada(): void
    {
        $presentacion = $this->presentacion();
        Configuracion::actual()->update(['envio_gratis_desde' => 0]);

        $this->actingAs(User::factory()->create(['role' => 'cliente']));
        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1]);

        // "total >= 0" daba verdadero siempre: el checkout le prometía "Gratis"
        // a todo el mundo mientras el carrito no mostraba nada.
        $this->get('/checkout')->assertOk()->assertSee('envioGratis&quot;:false', false);
    }

    public function test_con_la_promo_puesta_se_respeta_el_monto(): void
    {
        $presentacion = $this->presentacion();
        Configuracion::actual()->update(['envio_gratis_desde' => 10000]);

        $this->actingAs(User::factory()->create(['role' => 'cliente']));

        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1]);
        $this->get('/checkout')->assertOk()->assertSee('envioGratis&quot;:false', false);

        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1]);
        $this->get('/checkout')->assertOk()->assertSee('envioGratis&quot;:true', false);
    }
}
