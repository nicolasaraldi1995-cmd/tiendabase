<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El circuito completo: bajar la planilla, editarla y volver a subirla. Si los
 * títulos que exporta la tienda no fueran los que espera el importador, el
 * dueño tendría que acomodar el archivo a mano cada vez.
 */
class PlanillaExportarImportarTest extends TestCase
{
    use RefreshDatabase;

    private function catalogo(): Presentacion
    {
        $marca = Marca::create(['nombre' => 'Colmenar']);
        $categoria = Categoria::create(['nombre' => 'Mieles']);
        $producto = Producto::create([
            'nombre' => 'Miel de eucalipto',
            'marca_id' => $marca->id,
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Presentacion::create([
            'producto_id' => $producto->id,
            'unidad' => '500g',
            'precio' => 3500,
            'precio_mayorista' => 3100,
            'cantidad_mayorista' => 6,
            'stock' => 10,
            'activo' => true,
        ]);
    }

    public function test_un_cliente_no_puede_bajar_la_planilla(): void
    {
        $this->get(route('lista-precios.planilla'))->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->get(route('lista-precios.planilla'))
            ->assertForbidden();
    }

    public function test_lo_que_exporta_la_tienda_lo_lee_el_importador(): void
    {
        $this->catalogo();

        $csv = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('lista-precios.planilla'))
            ->assertOk()
            ->streamedContent();

        $ruta = tempnam(sys_get_temp_dir(), 'planilla_').'.csv';
        file_put_contents($ruta, $csv);

        $encabezados = (new ProductImportService)->getHeaders($ruta, 1);

        // Los mismos títulos que la plantilla que se entrega al negocio.
        $this->assertSame(
            ['nombre', 'marca', 'categoria', 'unidad', 'precio', 'precio_mayorista', 'cantidad_mayorista', 'stock'],
            $encabezados,
        );
    }

    /**
     * Excel ejecuta toda celda que empiece con "=". Un producto llamado
     * "=HYPERLINK(...)" corría en la máquina de quien bajara la planilla.
     */
    public function test_un_nombre_con_formula_sale_escapado(): void
    {
        $marca = Marca::create(['nombre' => 'Colmenar']);
        $producto = Producto::create([
            'nombre' => '=HYPERLINK("http://malo","click")',
            'marca_id' => $marca->id,
            'categoria_id' => Categoria::create(['nombre' => 'Mieles'])->id,
            'activo' => true,
        ]);
        Presentacion::create(['producto_id' => $producto->id, 'unidad' => '1u', 'precio' => 100, 'stock' => 1, 'activo' => true]);

        $csv = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('lista-precios.planilla'))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
