<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El filtro por etiqueta se aplicaba solo en la búsqueda y en el listado plano.
 * En las vistas por marca y por categoría se ignoraba —pero la pantalla igual
 * mostraba el filtro marcado como activo—, así que el cliente veía la lista
 * completa creyendo que estaba filtrada. Se llega por link guardado o
 * compartido, no navegando.
 */
class FiltroDeEtiquetaEnTodasLasVistasTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    private Categoria $categoria;

    private Etiqueta $etiqueta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marca = Marca::factory()->create(['nombre' => 'Colmenar', 'activo' => true]);
        $this->categoria = Categoria::factory()->create(['nombre' => 'Mieles', 'activo' => true]);
        $this->etiqueta = Etiqueta::create(['nombre' => 'Artesanal']);

        $this->producto('Miel artesanal', conEtiqueta: true);
        $this->producto('Miel industrial', conEtiqueta: false);
    }

    private function producto(string $nombre, bool $conEtiqueta): void
    {
        $producto = Producto::factory()->create([
            'nombre' => $nombre,
            'marca_id' => $this->marca->id,
            'categoria_id' => $this->categoria->id,
            'activo' => true,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 5000,
            'stock' => 10,
            'activo' => true,
        ]);

        if ($conEtiqueta) {
            $producto->etiquetas()->attach($this->etiqueta);
        }
    }

    /** Todas las formas de llegar a una lista de productos con el filtro puesto. */
    private function direcciones(): array
    {
        $m = $this->marca->id;
        $c = $this->categoria->id;
        $e = $this->etiqueta->id;

        return [
            'listado plano' => "/productos?etiqueta={$e}",
            'búsqueda' => "/productos?buscar=Miel&etiqueta={$e}",
            'marca' => "/productos?vista=marcas&marca={$m}&etiqueta={$e}",
            'marca y categoría' => "/productos?vista=marcas&marca={$m}&categoria={$c}&etiqueta={$e}",
            'categoría y marca' => "/productos?vista=categorias&categoria={$c}&marca={$m}&etiqueta={$e}",
        ];
    }

    public function test_el_filtro_de_etiqueta_manda_en_todas_las_vistas(): void
    {
        foreach ($this->direcciones() as $vista => $direccion) {
            $respuesta = $this->get($direccion)->assertOk();

            $respuesta->assertSee('Miel artesanal', false);
            $respuesta->assertDontSee(
                'Miel industrial',
                false,
            );

            $this->assertStringNotContainsString(
                'Miel industrial',
                (string) $respuesta->getContent(),
                "La vista «{$vista}» mostró un producto sin la etiqueta pedida: {$direccion}",
            );
        }
    }

    public function test_sin_el_filtro_se_ven_los_dos(): void
    {
        $contenido = (string) $this->get('/productos')->assertOk()->getContent();

        $this->assertStringContainsString('Miel artesanal', $contenido);
        $this->assertStringContainsString('Miel industrial', $contenido);
    }
}
