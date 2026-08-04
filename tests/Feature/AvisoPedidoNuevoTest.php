<?php

namespace Tests\Feature;

use App\Mail\PedidoNuevoMail;
use App\Models\Configuracion;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AvisoPedidoNuevoTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_avisa_al_negocio_cuando_entra_un_pedido(): void
    {
        Mail::fake();
        Configuracion::actual()->update(['email_avisos' => 'dueno@negocio.test']);

        $presentacion = Presentacion::factory()->create(['stock' => 10, 'precio' => 1000]);

        $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [(string) $presentacion->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro']);

        Mail::assertSent(PedidoNuevoMail::class, fn ($mail) => $mail->hasTo('dueno@negocio.test'));
    }

    public function test_sin_email_configurado_no_manda_nada(): void
    {
        Mail::fake();

        $presentacion = Presentacion::factory()->create(['stock' => 10, 'precio' => 1000]);

        $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [(string) $presentacion->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro']);

        Mail::assertNothingSent();
    }

    /**
     * El pedido ya está confirmado y el stock descontado cuando se manda el
     * aviso: si el mail falla, el cliente no puede perder su compra.
     */
    public function test_si_el_mail_falla_el_pedido_igual_queda(): void
    {
        Configuracion::actual()->update(['email_avisos' => 'dueno@negocio.test']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('servidor de mail caído'));

        $presentacion = Presentacion::factory()->create(['stock' => 10, 'precio' => 1000]);

        $this->actingAs(User::factory()->create())
            ->withSession(['cart' => [(string) $presentacion->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertRedirect();

        $this->assertDatabaseCount('pedidos', 1);
        $this->assertSame(8, $presentacion->fresh()->stock);
    }
}
