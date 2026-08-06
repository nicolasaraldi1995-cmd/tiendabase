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
            'hace_envios' => true,
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
        $this->assertTrue($actual->hace_envios);
        $this->assertTrue($actual->mostrar_lista_precios);
        $this->assertTrue($actual->mostrar_combos);
        $this->assertEquals(60000, $actual->envio_gratis_desde);
        $this->assertEquals(50000, $actual->pedido_minimo_mayorista);
    }

    /**
     * Mismo riesgo que el test de arriba, pero con las credenciales de
     * MercadoPago: los campos no se precargan (no se muestra un secreto en
     * pantalla), así que si se guardaran igual, entrar a Configuración a
     * cambiar cualquier cosa dejaría al negocio sin poder cobrar, en silencio.
     */
    public function test_guardar_no_borra_las_credenciales_de_mercadopago(): void
    {
        Configuracion::actual()->update([
            'nombre_negocio' => 'Almacén Viejo',
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'APP_USR-token-cargado',
            'mp_webhook_secret' => 'secreto-cargado',
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ConfiguracionPage::class)
            ->set('data.nombre_negocio', 'Almacén Nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $actual = Configuracion::actual()->fresh();

        $this->assertSame('Almacén Nuevo', $actual->nombre_negocio);
        $this->assertSame('APP_USR-token-cargado', $actual->tokenMercadoPago());
        $this->assertSame('secreto-cargado', $actual->secretoWebhookMercadoPago());
        $this->assertTrue($actual->puedeCobrarOnline());
    }

    /** Pero si el negocio escribe un token nuevo, ese sí tiene que guardarse. */
    public function test_un_token_nuevo_reemplaza_al_anterior(): void
    {
        Configuracion::actual()->update([
            'modo_cobro' => 'online_opcional',
            'mp_access_token' => 'APP_USR-el-viejo',
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ConfiguracionPage::class)
            ->set('data.mp_access_token', 'APP_USR-el-nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('APP_USR-el-nuevo', Configuracion::actual()->fresh()->tokenMercadoPago());
    }
}
