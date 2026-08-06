<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La columna "etiquetas" de la planilla crea las que no existen, y eso es
 * exactamente lo que la vuelve peligrosa: un archivo de proveedor con esa
 * columna mal armada (el separador equivocado, un export sucio) llenaba la
 * tienda de basura que después no había forma cómoda de sacar.
 */
class ImportadorEtiquetasTest extends TestCase
{
    use RefreshDatabase;

    /** Arma un CSV con las columnas que espera el importador. */
    private function planilla(array $filas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'import_').'.csv';
        $manejador = fopen($ruta, 'w');
        fputcsv($manejador, ['nombre', 'marca', 'categoria', 'unidad', 'precio', 'etiquetas']);

        foreach ($filas as $fila) {
            fputcsv($manejador, $fila);
        }

        fclose($manejador);

        return $ruta;
    }

    private function importar(array $filas, array $opciones = []): array
    {
        $mapa = [
            'nombre' => 'nombre', 'marca' => 'marca', 'categoria' => 'categoria',
            'unidad' => 'unidad', 'precio' => 'precio', 'etiquetas' => 'etiquetas',
        ];

        return (new ProductImportService)->import($this->planilla($filas), $mapa, 1, $opciones);
    }

    public function test_una_celda_con_muchas_etiquetas_se_corta_y_se_avisa(): void
    {
        $muchas = collect(range(1, 20))->map(fn ($i) => "Etiqueta {$i}")->implode(', ');

        $stats = $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', $muchas]]);

        $this->assertSame(8, Producto::first()->etiquetas()->count());
        // Antes las doce sobrantes desaparecían sin que el resumen dijera nada.
        $this->assertSame(12, $stats['etiquetas_descartadas']);
    }

    public function test_un_nombre_larguisimo_no_corta_la_importacion(): void
    {
        $largo = str_repeat('a', 400);

        $stats = $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', $largo]]);

        $this->assertSame([], $stats['errores']);
        $this->assertSame(1, Producto::count());
        $this->assertSame(255, mb_strlen(Etiqueta::first()->nombre));
    }

    /**
     * El tope por celda no frena nada a escala de archivo: muchas filas con
     * pocas etiquetas cada una crean igual miles.
     */
    public function test_un_archivo_que_pide_miles_de_etiquetas_se_frena(): void
    {
        $filas = collect(range(1, 300))
            ->map(fn ($i) => ["Producto {$i}", 'Colmenar', 'Mieles', '1u', '1000', "Etiqueta {$i}"])
            ->all();

        $stats = $this->importar($filas);

        $this->assertNotEmpty($stats['errores'], 'Tendría que haber avisado que la columna está mal armada.');
        $this->assertLessThan(300, Etiqueta::count());
    }

    /**
     * Las que crea la planilla no salen solas al menú de la tienda: que una
     * etiqueta sea filtro público es una decisión del dueño.
     */
    public function test_las_etiquetas_importadas_no_salen_solas_al_menu(): void
    {
        $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', 'Artesanal']]);

        $etiqueta = Etiqueta::where('nombre', 'Artesanal')->first();

        $this->assertTrue($etiqueta->activo);
        $this->assertFalse($etiqueta->en_filtros);
        $this->assertSame(0, Etiqueta::enFiltros()->count());
    }

    /** Una etiqueta que ya estaba en el menú no se apaga al reimportar. */
    public function test_no_le_apaga_el_filtro_a_una_etiqueta_que_ya_existia(): void
    {
        Etiqueta::create(['nombre' => 'Artesanal', 'en_filtros' => true]);

        $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', 'Artesanal']]);

        $this->assertTrue(Etiqueta::where('nombre', 'Artesanal')->first()->en_filtros);
    }

    /**
     * Con "actualizar existentes" apagado, las etiquetas se creaban igual y
     * quedaban sueltas: el dueño veía "0 productos actualizados" y se había
     * llenado la tabla.
     */
    public function test_sin_actualizar_existentes_no_crea_etiquetas_sueltas(): void
    {
        $marca = Marca::create(['nombre' => 'Colmenar']);
        Producto::create([
            'nombre' => 'Miel',
            'marca_id' => $marca->id,
            'categoria_id' => Categoria::create(['nombre' => 'Mieles'])->id,
            'activo' => true,
        ]);

        $stats = $this->importar(
            [['Miel', 'Colmenar', 'Mieles', '500 g', '5000', 'Artesanal, Importada']],
            ['actualizar_existentes' => false],
        );

        $this->assertSame(0, $stats['productos_actualizados']);
        $this->assertSame(0, Etiqueta::count(), 'Creó etiquetas de un producto que no llegó a actualizar.');
    }

    /**
     * Los espacios raros de Excel (el fijo, el fino) no los toca trim(), así
     * que "Artesanal" y "Artesanal " entraban como dos etiquetas idénticas a la
     * vista, y la sucia se quedaba con el nombre.
     */
    public function test_los_espacios_raros_no_generan_duplicadas(): void
    {
        $conEspaciosRaros = "Artesanal\u{00A0}, Artesanal, \u{00A0}Artesanal, Arte\u{2009}sanal";

        $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', $conEspaciosRaros]]);

        $nombres = Etiqueta::pluck('nombre')->all();

        $this->assertContains('Artesanal', $nombres, 'El nombre limpio nunca llegó a existir.');
        $this->assertSame(['Arte sanal', 'Artesanal'], collect($nombres)->sort()->values()->all());
    }

    /** "0" es un nombre plausible (talle 0, calibre 0) y se descartaba solo. */
    public function test_una_etiqueta_llamada_cero_se_puede_importar(): void
    {
        $this->importar([['Miel', 'Colmenar', 'Mieles', '500 g', '5000', '0']]);

        $this->assertSame(['0'], Etiqueta::pluck('nombre')->all());
    }
}
