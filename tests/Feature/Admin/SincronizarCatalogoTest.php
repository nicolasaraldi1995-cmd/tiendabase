<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\SincronizarCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, string $marca): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'marca_id' => Marca::firstOrCreate(['nombre' => $marca])->id,
            'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Prueba'])->id,
            'activo' => true,
        ]);
    }

    /**
     * La lista arranca directo con los títulos, igual que la plantilla que se
     * le entrega al negocio (por eso analizar() busca los encabezados en la
     * fila 1 y no en la 5).
     */
    private function lista(array $filas): string
    {
        $html = '<table><tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>';

        foreach ($filas as [$nombre, $marca]) {
            $html .= "<tr><td>{$nombre}</td><td>{$marca}</td><td>Prueba</td><td>1u</td><td>1.000,00</td></tr>";
        }

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html.'</table>');

        return $ruta;
    }

    public function test_detecta_y_aplica_el_cambio_de_marca(): void
    {
        $producto = $this->producto('Yogurt Valle de vainilla', 'Rincon');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Yogurt Valle de vainilla', 'Lacteos del Valle']]));

        $this->assertCount(1, $plan['cambiosDeMarca']);
        $this->assertSame('Lacteos del Valle', $plan['cambiosDeMarca'][0]['marcaNueva']);
        $this->assertCount(0, $plan['bajas']);

        $servicio->aplicar($plan);

        $this->assertSame('Lacteos del Valle', $producto->fresh()->marca->nombre);
        $this->assertTrue($producto->fresh()->activo, 'No tendría que darse de baja: es el mismo producto.');
    }

    public function test_detecta_y_aplica_el_cambio_de_nombre(): void
    {
        $producto = $this->producto('Yogurt Clasico de almendras sabor durazno', 'Granja Norte');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Yogurt Clasica de almendras sabor durazno', 'Granja Norte']]));

        $this->assertCount(1, $plan['cambiosDeNombre']);

        $servicio->aplicar($plan);

        $this->assertSame('Yogurt Clasica de almendras sabor durazno', $producto->fresh()->nombre);
    }

    /**
     * Renombrar no cambia el slug: los enlaces que ya circulan tienen que seguir
     * funcionando.
     */
    public function test_al_renombrar_no_cambia_la_direccion_web(): void
    {
        $producto = $this->producto('Yogurt Clasico de almendras sabor durazno', 'Granja Norte');
        $slug = $producto->slug;

        $servicio = app(SincronizarCatalogo::class);
        $servicio->aplicar($servicio->analizar($this->lista([['Yogurt Clasica de almendras sabor durazno', 'Granja Norte']])));

        $this->assertSame($slug, $producto->fresh()->slug);
    }

    public function test_da_de_baja_lo_que_no_esta_en_la_lista(): void
    {
        $producto = $this->producto('Producto discontinuado', 'Vegetal Co');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Otra cosa distinta', 'Otra marca']]));

        $this->assertCount(1, $plan['bajas']);

        $servicio->aplicar($plan);

        // Baja lógica: sigue existiendo, con su foto y su historial.
        $this->assertFalse($producto->fresh()->activo);
        $this->assertNotNull(Producto::withTrashed()->find($producto->id));
    }

    public function test_no_toca_lo_que_ya_coincide(): void
    {
        $producto = $this->producto('Queso cremoso', 'Bioalimento');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Queso cremoso', 'Bioalimento']]));

        $this->assertSame(1, $plan['sinCambios']);
        $this->assertCount(0, $plan['bajas']);
        $this->assertCount(0, $plan['cambiosDeMarca']);
        $this->assertCount(0, $plan['cambiosDeNombre']);
    }

    /**
     * El caso que más duele: la plantilla que se le entrega al negocio escribe
     * los títulos en minúscula, y la lista que exporta la web en mayúscula. Si
     * los títulos se dieran por sentados, subir la plantilla oficial no leería
     * ninguna fila y daría de baja el catálogo entero de una.
     */
    public function test_la_plantilla_con_titulos_en_minuscula_no_da_de_baja_nada(): void
    {
        $this->producto('Miel de eucalipto', 'Colmenar');

        $html = '<table><tr><td>nombre</td><td>marca</td><td>categoria</td><td>unidad</td><td>precio</td></tr>'
            .'<tr><td>Miel de eucalipto</td><td>Colmenar</td><td>Prueba</td><td>1u</td><td>1.000,00</td></tr></table>';
        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html);

        $plan = app(SincronizarCatalogo::class)->analizar($ruta);

        $this->assertSame(1, $plan['sinCambios']);
        $this->assertCount(0, $plan['bajas']);
    }

    /**
     * Y si el archivo no trae esas columnas, no se toca nada: dar de baja el
     * catálogo porque no se entendió el archivo sería el peor error posible.
     */
    public function test_un_archivo_sin_las_columnas_no_da_de_baja_nada(): void
    {
        $this->producto('Miel de eucalipto', 'Colmenar');

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, '<table><tr><td>cosa</td><td>otra</td></tr><tr><td>a</td><td>b</td></tr></table>');

        $plan = app(SincronizarCatalogo::class)->analizar($ruta);

        $this->assertCount(0, $plan['bajas']);
        $this->assertCount(0, $plan['cambiosDeMarca']);
    }

    /**
     * Un nombre parecido pero de otra marca no se toma como renombrado: hay
     * demasiado margen para equivocarse y terminar pisando otro producto.
     */
    public function test_un_nombre_parecido_de_otra_marca_no_cuenta_como_renombrado(): void
    {
        $this->producto('Leche de almendras chocolatada', 'Granja Norte');

        $plan = app(SincronizarCatalogo::class)
            ->analizar($this->lista([['Leche de almendras chocolatadaa', 'Vrink']]));

        $this->assertCount(0, $plan['cambiosDeNombre']);
        $this->assertCount(1, $plan['bajas']);
    }
}
