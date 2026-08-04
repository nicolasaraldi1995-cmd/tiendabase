<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Configuracion as ConfiguracionPage;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracionGuardarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guardar un cambio suelto (el nombre del negocio) no puede apagar de
     * rebote los interruptores ni borrar el resto de la configuración: sería
     * una pérdida silenciosa de datos del cliente.
     */
    public function test_guardar_no_apaga_lo_que_no_se_toco(): void
    {
        Configuracion::actual()->update([
            'nombre_negocio' => 'Almacén Viejo',
            'whatsapp' => '5492477504048',
            'mostrar_filtros_alimentos' => true,
            'mostrar_lista_precios' => true,
            'mostrar_combos' => true,
            'envio_gratis_desde' => 60000,
            'pedido_minimo_mayorista' => 50000,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ConfiguracionPage::class)
            ->set('data.nombre_negocio', 'Almacén Nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $actual = Configuracion::actual()->fresh();

        $this->assertSame('Almacén Nuevo', $actual->nombre_negocio);
        $this->assertSame('5492477504048', $actual->whatsapp);
        $this->assertTrue($actual->mostrar_filtros_alimentos);
        $this->assertTrue($actual->mostrar_lista_precios);
        $this->assertTrue($actual->mostrar_combos);
        $this->assertEquals(60000, $actual->envio_gratis_desde);
        $this->assertEquals(50000, $actual->pedido_minimo_mayorista);
    }
}
