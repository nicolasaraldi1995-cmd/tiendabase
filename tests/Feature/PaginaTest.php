<?php

namespace Tests\Feature;

use App\Models\Pagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_pagina_publicada_se_ve_en_la_tienda(): void
    {
        Pagina::create(['titulo' => 'Cómo comprar', 'contenido' => '<p>Elegí y pedí.</p>']);

        $this->get('/p/como-comprar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pagina')
            ->where('pagina.titulo', 'Cómo comprar'));
    }

    public function test_una_pagina_apagada_no_se_puede_abrir(): void
    {
        Pagina::create(['titulo' => 'Borrador', 'activo' => false]);

        $this->get('/p/borrador')->assertNotFound();
    }

    public function test_las_paginas_publicadas_viajan_a_la_tienda_para_el_pie(): void
    {
        Pagina::create(['titulo' => 'Nosotros', 'orden' => 1]);
        Pagina::create(['titulo' => 'Apagada', 'activo' => false]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('paginas', 1)
            ->where('paginas.0.titulo', 'Nosotros'));
    }

    public function test_dos_paginas_con_el_mismo_titulo_no_chocan(): void
    {
        Pagina::create(['titulo' => 'Envíos']);
        $segunda = Pagina::create(['titulo' => 'Envíos']);

        $this->assertSame('envios-2', $segunda->slug);
    }
}
