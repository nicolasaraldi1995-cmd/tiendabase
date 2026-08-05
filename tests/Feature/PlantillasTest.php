<?php

namespace Tests\Feature;

use App\Filament\Pages\Configuracion as PaginaConfiguracion;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\RestaurarTienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El aspecto de la tienda (plantilla y tipografía) lo elige el negocio desde el
 * panel. Acá se cuida que lo elegido llegue a la página y que una elección rota
 * no deje la tienda en blanco.
 */
class PlantillasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una plantilla declarada en el panel pero sin carpeta se ve exactamente
     * igual que Catálogo, porque todo cae al motor: el dueño la elige, no pasa
     * nada y no hay error que lo explique. Es el único modo de romper esto que
     * no se nota mirando la pantalla.
     */
    public function test_cada_plantilla_del_panel_existe_en_disco(): void
    {
        foreach (array_keys(Configuracion::PLANTILLAS) as $clave) {
            $carpeta = resource_path('js/Plantillas/'.$clave);

            $this->assertDirectoryExists($carpeta, "La plantilla '{$clave}' se ofrece en el panel pero no tiene carpeta.");

            $this->assertNotEmpty(
                glob($carpeta.'/*.vue') ?: [],
                "La plantilla '{$clave}' no pisa ningún componente: se vería idéntica a Catálogo."
            );
        }
    }

    /**
     * Si se renombra una carpeta o se restaura una base vieja, vale más la
     * tienda con el aspecto default que una tienda que no carga.
     */
    public function test_una_plantilla_que_ya_no_existe_cae_en_la_default(): void
    {
        Configuracion::actual()->update(['plantilla' => 'la-que-borre', 'tipografia' => 'comic']);

        $config = Configuracion::actual();

        $this->assertSame('catalogo', $config->plantilla());
        $this->assertSame('inter', $config->tipografia());
    }

    public function test_la_tienda_declara_la_plantilla_y_la_fuente_elegidas(): void
    {
        Configuracion::actual()->update(['plantilla' => 'vidriera', 'tipografia' => 'lora']);

        $respuesta = $this->get('/');

        // Lo lee el resolver de app.js para saber en qué carpeta buscar.
        $respuesta->assertSee('window.__plantilla = "vidriera"', false);
        $respuesta->assertSee('fonts.bunny.net/css?family=lora:', false);
        $respuesta->assertSee("--fuente: 'Lora'", false);
    }

    /**
     * Sin esto el navegador se baja dos veces la misma pantalla: la del motor
     * porque la precarga el blade, y la de la plantilla porque es la que se usa.
     */
    public function test_precarga_la_pantalla_de_la_plantilla_solo_si_la_pisa(): void
    {
        Configuracion::actual()->update(['plantilla' => 'vidriera']);
        $config = Configuracion::actual();

        $this->assertSame(
            'resources/js/Plantillas/vidriera/Pages/Home.vue',
            $config->vistaDeLaPagina('Home'),
        );

        // El carrito no lo pisa ninguna plantilla: lo hereda del motor.
        $this->assertSame('resources/js/Pages/Cart.vue', $config->vistaDeLaPagina('Cart'));
    }

    public function test_el_dueno_elige_plantilla_y_tipografia_desde_el_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(PaginaConfiguracion::class)
            ->fillForm(['plantilla' => 'mostrador', 'tipografia' => 'archivo'])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $config = Configuracion::actual()->fresh();
        $this->assertSame('mostrador', $config->plantilla);
        $this->assertSame('archivo', $config->tipografia);
    }

    public function test_el_panel_no_acepta_una_plantilla_inventada(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(PaginaConfiguracion::class)
            ->fillForm(['plantilla' => 'la-mia'])
            ->call('guardar')
            ->assertHasFormErrors(['plantilla']);
    }

    public function test_restaurar_de_fabrica_vuelve_al_aspecto_original(): void
    {
        Configuracion::actual()->update(['plantilla' => 'carta', 'tipografia' => 'poppins']);

        (new RestaurarTienda)->ejecutar();

        $config = Configuracion::actual()->fresh();
        $this->assertSame('catalogo', $config->plantilla);
        $this->assertSame('inter', $config->tipografia);
    }
}
