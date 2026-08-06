<?php

namespace Tests\Feature;

use App\Services\ProductImportService;
use Tests\TestCase;

/**
 * La planilla modelo (plantilla-catalogo.xlsx, en la raíz del repo) se entrega
 * al negocio junto con GUIA-CARGA-CATALOGO.md: la llena afuera y la sube al
 * Importador. Este test garantiza que el archivo commiteado siga siendo
 * legible por el propio importador con los encabezados esperados en la fila 1.
 */
class PlantillaCatalogoTest extends TestCase
{
    public function test_la_planilla_modelo_se_lee_con_el_propio_importador(): void
    {
        $headers = (new ProductImportService)->getHeaders(base_path('plantilla-catalogo.xlsx'), 1);

        $this->assertSame(
            ['nombre', 'marca', 'categoria', 'unidad', 'precio', 'precio_mayorista', 'cantidad_mayorista', 'stock', 'etiquetas', 'nuevo'],
            $headers,
        );
    }
}
