<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Etiqueta;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las etiquetas reemplazan a "Sin TACC / Frío / Congelado", que eran tres
 * columnas fijas en `productos`: una ferretería tenía tres casillas que no le
 * servían y ninguna que sí.
 *
 * Lo que hay que cuidar es que las dos cosas que las columnas hacían de más
 * (salir como filtro en el menú y disparar un aviso en el carrito) sigan
 * funcionando ahora que las decide el negocio.
 */
class EtiquetasLibresTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, Etiqueta ...$etiquetas): Producto
    {
        $producto = Producto::factory()->create([
            'nombre' => $nombre,
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 5000,
            'stock' => 50,
            'activo' => true,
        ]);

        $producto->etiquetas()->attach(collect($etiquetas)->pluck('id'));

        return $producto;
    }

    public function test_las_etiquetas_marcadas_salen_como_filtro_en_el_menu(): void
    {
        Etiqueta::create(['nombre' => 'Inoxidable', 'en_filtros' => true, 'orden' => 1]);
        Etiqueta::create(['nombre' => 'Importado', 'en_filtros' => false, 'orden' => 2]);
        Etiqueta::create(['nombre' => 'Descontinuada', 'en_filtros' => true, 'activo' => false]);

        $contenido = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Inoxidable', $contenido);
        // Sin el interruptor no molesta en el menú, aunque siga usándose.
        $this->assertStringNotContainsString('Importado', $contenido);
        // Apagada no sale ni aunque esté marcada como filtro.
        $this->assertStringNotContainsString('Descontinuada', $contenido);
    }

    public function test_el_filtro_del_menu_muestra_solo_esos_productos(): void
    {
        $etiqueta = Etiqueta::create(['nombre' => 'Inoxidable']);
        $this->producto('Tornillo inoxidable', $etiqueta);
        $this->producto('Tornillo común');

        $contenido = (string) $this->get('/productos?etiqueta='.$etiqueta->id)->assertOk()->getContent();

        $this->assertStringContainsString('Tornillo inoxidable', $contenido);
        $this->assertStringNotContainsString('Tornillo común', $contenido);
    }

    /**
     * Es lo que generaliza la cadena de frío: donde la dietética ponía
     * "consultá disponibilidad", la ferretería pone "bajo pedido".
     */
    public function test_una_etiqueta_con_aviso_avisa_en_el_carrito(): void
    {
        $etiqueta = Etiqueta::create([
            'nombre' => 'Bajo pedido',
            'aviso' => 'Bajo pedido: puede demorar 5 días.',
        ]);
        $producto = $this->producto('Caño de bronce', $etiqueta);

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        // El payload de Inertia viaja escapado, así que se mira el dato.
        $this->get('/carrito')->assertOk()->assertInertia(
            fn ($page) => $page->where('avisos.0.texto', 'Bajo pedido: puede demorar 5 días.')
        );
    }

    public function test_sin_aviso_no_molesta_a_nadie(): void
    {
        $producto = $this->producto('Caño común', Etiqueta::create(['nombre' => 'Importado']));

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->get('/carrito')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('avisos', []));
    }

    /** El cliente que ya conoce la condición no necesita que se la repitan. */
    public function test_al_cliente_marcado_no_se_le_muestran_los_avisos(): void
    {
        $etiqueta = Etiqueta::create(['nombre' => 'Bajo pedido', 'aviso' => 'Puede demorar.']);
        $producto = $this->producto('Caño de bronce', $etiqueta);

        $this->actingAs(User::factory()->create(['role' => 'cliente', 'omite_avisos' => true]))
            ->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->get('/carrito')->assertOk()->assertInertia(fn ($page) => $page->where('avisos', []));
    }

    /**
     * Dos etiquetas con el mismo texto (el caso de "Frío" y "Congelado", que
     * salen de la misma condición) tienen que mostrar un cartel, no dos.
     */
    public function test_dos_etiquetas_con_el_mismo_aviso_muestran_un_solo_cartel(): void
    {
        $frio = Etiqueta::create(['nombre' => 'Frío', 'aviso' => 'Consultá disponibilidad.']);
        $congelado = Etiqueta::create(['nombre' => 'Congelado', 'aviso' => 'Consultá disponibilidad.']);

        $producto = $this->producto('Helado', $frio, $congelado);

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->get('/carrito')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('avisos', 1));
    }

    public function test_se_pueden_sacar_del_carrito_los_de_una_etiqueta(): void
    {
        $etiqueta = Etiqueta::create(['nombre' => 'Bajo pedido', 'aviso' => 'Puede demorar.']);
        $conEtiqueta = $this->producto('Caño de bronce', $etiqueta);
        $sinEtiqueta = $this->producto('Caño común');

        $this->actingAs(User::factory()->create(['role' => 'cliente']));
        $this->post('/carrito/add', ['presentacion_id' => $conEtiqueta->presentaciones()->first()->id, 'cantidad' => 1]);
        $this->post('/carrito/add', ['presentacion_id' => $sinEtiqueta->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->assertCount(2, session('cart'));

        $this->delete('/carrito/remove-etiqueta', ['etiqueta_id' => $etiqueta->id]);

        $this->assertSame(
            [$sinEtiqueta->presentaciones()->first()->id],
            array_map('intval', array_keys(session('cart'))),
        );
    }

    public function test_sin_envios_el_checkout_no_acepta_envio_a_domicilio(): void
    {
        Configuracion::actual()->update(['hace_envios' => false]);
        $producto = $this->producto('Caño común');

        $this->actingAs(User::factory()->create(['role' => 'cliente']));
        $this->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->post('/checkout', ['entrega' => 'envio'])->assertSessionHasErrors('entrega');
        $this->post('/checkout', ['entrega' => 'retiro'])->assertSessionHasNoErrors();
    }

    /**
     * El negocio recibía pedidos para entregar sin saber dónde ni a quién
     * llamar, porque la dirección vive en el perfil y nadie la comprobaba.
     */
    public function test_no_se_puede_pedir_a_domicilio_sin_domicilio(): void
    {
        Configuracion::actual()->update(['hace_envios' => true]);
        $producto = $this->producto('Caño común');

        $sinDatos = User::factory()->create(['role' => 'cliente', 'direccion' => null, 'celular' => null]);

        $this->actingAs($sinDatos);
        $this->post('/carrito/add', ['presentacion_id' => $producto->presentaciones()->first()->id, 'cantidad' => 1]);

        $this->post('/checkout', ['entrega' => 'envio'])->assertSessionHasErrors('entrega');

        // Con los datos cargados, pasa.
        $sinDatos->update(['direccion' => 'Calle 123', 'celular' => '1122334455']);

        $this->post('/checkout', ['entrega' => 'envio'])->assertSessionHasNoErrors();
    }
}
