<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\SeccionMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuTiendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tienda_arranca_con_el_menu_de_siempre(): void
    {
        // La migración siembra el menú que antes estaba escrito en el código:
        // una tienda que actualiza no tiene que ver ningún cambio.
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu.0.titulo', 'Inicio')
            ->where('menu.1.titulo', 'Categorías')
            ->where('menu.2.titulo', 'Marcas'));
    }

    public function test_el_negocio_agrega_su_propia_seccion_con_emoji(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Juguetes']);

        SeccionMenu::create([
            'titulo' => 'Juguetes',
            'emoji' => '🧸',
            'destino_tipo' => 'categoria',
            'destino_valor' => (string) $categoria->id,
            'orden' => 5,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu.0.titulo', 'Juguetes')
            ->where('menu.0.emoji', '🧸')
            ->where('menu.0.url', route('productos.index', ['vista' => 'categorias', 'categoria' => $categoria->id])));
    }

    public function test_una_seccion_apagada_no_aparece(): void
    {
        SeccionMenu::query()->delete();
        SeccionMenu::create(['titulo' => 'Escondida', 'destino_tipo' => 'ofertas', 'activo' => false]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('menu', 0));
    }

    /**
     * Si el dueño borra la categoría a la que apuntaba un ítem, el menú lo
     * saltea en vez de mostrarle al cliente un link que no lleva a ningún lado.
     */
    public function test_una_seccion_que_apunta_a_algo_borrado_se_saltea(): void
    {
        SeccionMenu::query()->delete();
        SeccionMenu::create(['titulo' => 'Rota', 'destino_tipo' => 'categoria', 'destino_valor' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('menu', 0));
    }

    public function test_el_orden_lo_define_el_negocio(): void
    {
        SeccionMenu::query()->delete();
        SeccionMenu::create(['titulo' => 'Segunda', 'destino_tipo' => 'ofertas', 'orden' => 20]);
        SeccionMenu::create(['titulo' => 'Primera', 'destino_tipo' => 'nuevos', 'orden' => 10]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu.0.titulo', 'Primera')
            ->where('menu.1.titulo', 'Segunda'));
    }
}
