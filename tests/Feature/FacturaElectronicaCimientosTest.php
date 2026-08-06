<?php

namespace Tests\Feature;

use App\Models\Comprobante;
use App\Models\Configuracion;
use App\Models\Pedido;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Los cimientos de la factura electrónica. Igual que con el cobro online, lo
 * más importante de esta tanda es que con la facturación sin activar la tienda
 * sigue funcionando exactamente como antes.
 */
class FacturaElectronicaCimientosTest extends TestCase
{
    use RefreshDatabase;

    /** Certificado y clave de mentira: acá no se firma nada, solo se guarda. */
    private function conFacturacion(array $extra = []): void
    {
        Configuracion::actual()->update([
            'factura_activa' => true,
            'cuit' => '20123456789',
            'punto_venta' => 3,
            'condicion_iva' => 'monotributo',
            'arca_certificado' => '-----BEGIN CERTIFICATE-----\nasd\n-----END CERTIFICATE-----',
            'arca_clave_privada' => '-----BEGIN PRIVATE KEY-----\nqwe\n-----END PRIVATE KEY-----',
            ...$extra,
        ]);
    }

    public function test_una_tienda_recien_instalada_no_factura(): void
    {
        $config = Configuracion::actual();

        $this->assertFalse($config->factura_activa);
        $this->assertFalse($config->puedeFacturar());
    }

    /**
     * Prender el interruptor no alcanza. Si esto devolviera true sin
     * credenciales, cada pedido intentaría facturar contra ARCA sin poder, y el
     * cliente vería un error en el checkout por algo que no es su problema.
     */
    public function test_prender_el_interruptor_sin_credenciales_no_alcanza(): void
    {
        Configuracion::actual()->update(['factura_activa' => true]);

        $this->assertFalse(Configuracion::actual()->puedeFacturar());
    }

    /** @return array<string, array{string}> */
    public static function camposQueFaltan(): array
    {
        return [
            'sin CUIT' => ['cuit'],
            'sin punto de venta' => ['punto_venta'],
            'sin condición frente al IVA' => ['condicion_iva'],
            'sin certificado' => ['arca_certificado'],
            'sin clave privada' => ['arca_clave_privada'],
        ];
    }

    #[DataProvider('camposQueFaltan')]
    public function test_falta_cualquiera_de_los_datos_y_no_se_factura(string $campo): void
    {
        $this->conFacturacion([$campo => null]);

        $this->assertFalse(
            Configuracion::actual()->puedeFacturar(),
            "Se habilitó la facturación con «{$campo}» vacío.",
        );
    }

    public function test_con_todo_cargado_si_se_puede_facturar(): void
    {
        $this->conFacturacion();

        $this->assertTrue(Configuracion::actual()->puedeFacturar());
    }

    /**
     * El default es homologación: los comprobantes de prueba no tienen validez
     * legal, y equivocarse hacia ese lado no le rompe la contabilidad a nadie.
     * Al revés sí.
     */
    public function test_el_ambiente_por_defecto_es_de_pruebas(): void
    {
        $this->conFacturacion();

        $this->assertTrue(Configuracion::actual()->arcaEnHomologacion());

        Configuracion::actual()->update(['arca_ambiente' => 'produccion']);
        $this->assertFalse(Configuracion::actual()->arcaEnHomologacion());
    }

    public function test_el_monotributista_emite_factura_c(): void
    {
        $this->conFacturacion(['condicion_iva' => 'monotributo']);
        $this->assertTrue(Configuracion::actual()->emiteFacturaC());

        Configuracion::actual()->update(['condicion_iva' => 'responsable_inscripto']);
        $this->assertFalse(Configuracion::actual()->emiteFacturaC());
    }

    /**
     * Mismo caso que con MercadoPago: si se regeneró la APP_KEY, las
     * credenciales quedan indescifrables. Lo único que puede pasar es que la
     * facturación se apague, nunca que la tienda deje de funcionar.
     */
    public function test_un_certificado_indescifrable_apaga_la_facturacion_pero_no_rompe_la_tienda(): void
    {
        $this->conFacturacion();

        DB::table('configuraciones')->where('id', 1)->update([
            'arca_certificado' => 'esto-no-se-puede-descifrar',
        ]);

        $config = Configuracion::actual()->fresh();

        $this->assertNull($config->certificadoArca());
        $this->assertFalse($config->puedeFacturar());
        $this->get('/')->assertOk();
    }

    // ─── El comprobante ───────────────────────────────────────────────────

    public function test_el_comprobante_se_lee_como_en_el_papel(): void
    {
        $comprobante = new Comprobante(['punto_venta' => 3, 'numero' => 127, 'tipo' => Comprobante::FACTURA_C]);

        $this->assertSame('0003-00000127', $comprobante->numeroCompleto());
        $this->assertSame('Factura C', $comprobante->nombre());
        $this->assertFalse($comprobante->esNotaDeCredito());
    }

    public function test_cada_factura_sabe_que_nota_de_credito_le_corresponde(): void
    {
        $this->assertSame(Comprobante::NOTA_CREDITO_A, Comprobante::NOTA_DE_CREDITO_DE[Comprobante::FACTURA_A]);
        $this->assertSame(Comprobante::NOTA_CREDITO_B, Comprobante::NOTA_DE_CREDITO_DE[Comprobante::FACTURA_B]);
        $this->assertSame(Comprobante::NOTA_CREDITO_C, Comprobante::NOTA_DE_CREDITO_DE[Comprobante::FACTURA_C]);

        // Y todas las notas se reconocen como tales.
        foreach (Comprobante::TIPOS_DE_NOTA_DE_CREDITO as $tipo) {
            $this->assertTrue((new Comprobante(['tipo' => $tipo]))->esNotaDeCredito());
        }
    }

    /**
     * La numeración de ARCA es correlativa por punto de venta y tipo. Este
     * índice es lo que impide que dos emisiones a la vez se lleven el mismo
     * número, que del lado de ARCA sería un comprobante duplicado.
     */
    public function test_no_puede_haber_dos_comprobantes_con_el_mismo_numero(): void
    {
        $pedido = Pedido::factory()->create();

        $datos = [
            'pedido_id' => $pedido->id,
            'tipo' => Comprobante::FACTURA_C,
            'punto_venta' => 3,
            'numero' => 500,
            'cae' => '75123456789012',
            'cae_vencimiento' => now()->addDays(10),
            'fecha' => now(),
            'neto' => 1000, 'iva' => 0, 'total' => 1000,
            'receptor_doc_tipo' => Comprobante::DOC_CONSUMIDOR_FINAL,
            'receptor_doc_nro' => '0',
        ];

        Comprobante::create($datos);

        $this->expectException(QueryException::class);
        Comprobante::create($datos);
    }

    /** Pero el mismo número en otro tipo de comprobante es legítimo. */
    public function test_el_mismo_numero_en_otro_tipo_de_comprobante_convive(): void
    {
        $pedido = Pedido::factory()->create();

        $base = [
            'pedido_id' => $pedido->id,
            'punto_venta' => 3,
            'numero' => 500,
            'cae' => '75123456789012',
            'cae_vencimiento' => now()->addDays(10),
            'fecha' => now(),
            'neto' => 1000, 'iva' => 0, 'total' => 1000,
            'receptor_doc_tipo' => Comprobante::DOC_CONSUMIDOR_FINAL,
            'receptor_doc_nro' => '0',
        ];

        Comprobante::create($base + ['tipo' => Comprobante::FACTURA_C]);
        Comprobante::create($base + ['tipo' => Comprobante::NOTA_CREDITO_C]);

        $this->assertSame(2, Comprobante::count());
    }

    /**
     * Un pedido se factura una sola vez: dos facturas le duplicarían el IVA al
     * negocio y le dejarían dos comprobantes por la misma venta al cliente.
     */
    public function test_un_pedido_sabe_si_ya_se_facturo(): void
    {
        $pedido = Pedido::factory()->create();

        $this->assertFalse($pedido->yaSeFacturo());

        Comprobante::create([
            'pedido_id' => $pedido->id,
            'tipo' => Comprobante::FACTURA_C,
            'punto_venta' => 3, 'numero' => 1,
            'cae' => '75123456789012',
            'cae_vencimiento' => now()->addDays(10),
            'fecha' => now(),
            'neto' => 1000, 'iva' => 0, 'total' => 1000,
            'receptor_doc_tipo' => Comprobante::DOC_CONSUMIDOR_FINAL,
            'receptor_doc_nro' => '0',
        ]);

        $this->assertTrue($pedido->fresh()->yaSeFacturo());
        $this->assertNotNull($pedido->fresh()->factura());
    }

    /** Una nota de crédito sola no cuenta como "ya facturado". */
    public function test_una_nota_de_credito_no_cuenta_como_factura(): void
    {
        $pedido = Pedido::factory()->create();

        Comprobante::create([
            'pedido_id' => $pedido->id,
            'tipo' => Comprobante::NOTA_CREDITO_C,
            'punto_venta' => 3, 'numero' => 1,
            'cae' => '75123456789012',
            'cae_vencimiento' => now()->addDays(10),
            'fecha' => now(),
            'neto' => 1000, 'iva' => 0, 'total' => 1000,
            'receptor_doc_tipo' => Comprobante::DOC_CONSUMIDOR_FINAL,
            'receptor_doc_nro' => '0',
        ]);

        $this->assertFalse($pedido->fresh()->yaSeFacturo());
        $this->assertNull($pedido->fresh()->factura());
    }
}
