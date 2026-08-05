<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecuperarAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_las_cuentas_con_su_rol(): void
    {
        User::factory()->create(['email' => 'jefe@tienda.test', 'role' => 'admin']);
        User::factory()->create(['email' => 'cliente@tienda.test', 'role' => 'cliente']);

        $this->artisan('usuarios:listar')
            ->expectsOutputToContain('jefe@tienda.test')
            ->expectsOutputToContain('cliente@tienda.test')
            ->assertSuccessful();
    }

    public function test_filtra_por_rol(): void
    {
        User::factory()->create(['email' => 'jefe@tienda.test', 'role' => 'admin']);
        User::factory()->create(['email' => 'cliente@tienda.test', 'role' => 'cliente']);

        $this->artisan('usuarios:listar --rol=admin')
            ->expectsOutputToContain('jefe@tienda.test')
            ->doesntExpectOutputToContain('cliente@tienda.test')
            ->assertSuccessful();
    }

    public function test_cambia_la_clave_y_permite_entrar(): void
    {
        $usuario = User::factory()->create(['email' => 'jefe@tienda.test', 'role' => 'admin']);

        $this->artisan('usuarios:clave jefe@tienda.test claveNueva123')->assertSuccessful();

        $this->assertTrue(Hash::check('claveNueva123', $usuario->fresh()->password));
    }

    public function test_avisa_si_el_correo_no_existe(): void
    {
        $this->artisan('usuarios:clave nadie@tienda.test claveNueva123')->assertFailed();
    }

    public function test_no_acepta_una_clave_corta(): void
    {
        $usuario = User::factory()->create(['email' => 'jefe@tienda.test', 'role' => 'admin']);
        $original = $usuario->password;

        $this->artisan('usuarios:clave jefe@tienda.test corta')->assertFailed();

        $this->assertSame($original, $usuario->fresh()->password);
    }
}
