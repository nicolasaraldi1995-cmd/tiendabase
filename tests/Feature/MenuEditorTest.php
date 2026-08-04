<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\SeccionMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * El editor del menú vive en la tienda pública, así que sus rutas escriben en
 * la configuración del negocio desde afuera del panel: el candado importa.
 */
class MenuEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_un_cliente_no_puede_tocar_el_menu(): void
    {
        $seccion = SeccionMenu::first();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($cliente)->post(route('menu-tienda.store'), [
            'titulo' => 'Colada', 'destino_tipo' => 'ofertas',
        ])->assertForbidden();

        $this->actingAs($cliente)
            ->patch(route('menu-tienda.update', $seccion), ['titulo' => 'Hackeado', 'activo' => true])
            ->assertForbidden();

        $this->actingAs($cliente)->delete(route('menu-tienda.destroy', $seccion))->assertForbidden();
        $this->actingAs($cliente)->post(route('menu-tienda.reordenar'), ['ids' => [$seccion->id]])->assertForbidden();
    }

    /** Ni el operador: el menú es del dueño. */
    public function test_el_operador_tampoco(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->post(route('menu-tienda.store'), ['titulo' => 'Nueva', 'destino_tipo' => 'ofertas'])
            ->assertForbidden();
    }

    public function test_un_invitado_va_al_login(): void
    {
        $this->post(route('menu-tienda.store'), ['titulo' => 'Nueva', 'destino_tipo' => 'ofertas'])
            ->assertRedirect('/login');
    }

    public function test_el_dueno_agrega_una_seccion_desde_la_tienda(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Juguetes']);

        $this->actingAs($this->admin())->post(route('menu-tienda.store'), [
            'titulo' => 'Juguetes',
            'emoji' => '🧸',
            'destino_tipo' => 'categoria',
            'destino_valor' => (string) $categoria->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('secciones_menu', ['titulo' => 'Juguetes', 'emoji' => '🧸', 'activo' => true]);
    }

    public function test_un_destino_que_necesita_valor_no_puede_ir_vacio(): void
    {
        $this->actingAs($this->admin())->post(route('menu-tienda.store'), [
            'titulo' => 'Rota',
            'destino_tipo' => 'categoria',
        ])->assertSessionHasErrors('destino_valor');
    }

    public function test_reordenar_deja_el_menu_en_el_orden_pedido(): void
    {
        SeccionMenu::query()->delete();
        $a = SeccionMenu::create(['titulo' => 'A', 'destino_tipo' => 'ofertas', 'orden' => 10]);
        $b = SeccionMenu::create(['titulo' => 'B', 'destino_tipo' => 'nuevos', 'orden' => 20]);

        $this->actingAs($this->admin())
            ->post(route('menu-tienda.reordenar'), ['ids' => [$b->id, $a->id]])
            ->assertSessionHasNoErrors();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu.0.titulo', 'B')
            ->where('menu.1.titulo', 'A'));
    }

    public function test_apagar_una_seccion_la_saca_de_la_tienda(): void
    {
        $seccion = SeccionMenu::where('destino_tipo', 'nuevos')->firstOrFail();

        $this->actingAs($this->admin())->patch(route('menu-tienda.update', $seccion), [
            'titulo' => $seccion->titulo,
            'emoji' => $seccion->emoji,
            'activo' => false,
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu', fn ($menu) => collect($menu)->doesntContain('titulo', 'Nuevos')));
    }

    /** Las opciones para armar el menú son datos internos: solo las ve el dueño. */
    public function test_las_opciones_del_editor_solo_viajan_para_el_dueno(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('menuEditor', null));

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->get('/')->assertInertia(fn (Assert $page) => $page->where('menuEditor', null));

        $this->actingAs($this->admin())
            ->get('/')->assertInertia(fn (Assert $page) => $page->has('menuEditor.destinos'));
    }
}
