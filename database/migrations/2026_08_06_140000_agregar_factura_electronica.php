<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factura electrónica de ARCA (ex AFIP).
 *
 * Las credenciales son de cada negocio y son fiscales: el certificado y su
 * clave privada permiten emitir comprobantes a nombre de ese CUIT. Van
 * encriptadas, igual que las de MercadoPago.
 *
 * `comprobantes` guarda SOLO los que ARCA autorizó de verdad. Un intento
 * fallido no deja fila: el número que se iba a usar no se consumió del lado de
 * ARCA, y anotarlo acá haría que nuestro registro dejara de coincidir con el de
 * ellos, que es el único que vale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->boolean('factura_activa')->default(false);
            $table->string('cuit', 11)->nullable();
            $table->unsignedSmallInteger('punto_venta')->nullable();
            // 'monotributo' emite siempre C; 'responsable_inscripto' emite A o B
            // según a quién le venda.
            $table->string('condicion_iva')->nullable();
            $table->text('arca_certificado')->nullable();
            $table->text('arca_clave_privada')->nullable();
            // Arranca en homologación: los comprobantes de prueba no tienen
            // validez legal, y equivocarse hacia ese lado no le rompe la
            // contabilidad a nadie.
            $table->string('arca_ambiente')->default('homologacion');
        });

        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->restrictOnDelete();

            // Los códigos de ARCA: 1 factura A, 6 factura B, 11 factura C,
            // 3/8/13 las notas de crédito de cada una.
            $table->unsignedSmallInteger('tipo');
            $table->unsignedSmallInteger('punto_venta');
            $table->unsignedInteger('numero');

            $table->string('cae', 20);
            $table->date('cae_vencimiento');
            $table->date('fecha');

            $table->decimal('neto', 12, 2);
            $table->decimal('iva', 12, 2);
            $table->decimal('total', 12, 2);

            // 80 CUIT, 96 DNI, 99 consumidor final (con número 0).
            $table->unsignedSmallInteger('receptor_doc_tipo');
            $table->string('receptor_doc_nro', 20);

            // La respuesta cruda de ARCA, para poder reconstruir qué pasó sin
            // depender de cómo la hayamos interpretado ese día.
            $table->json('respuesta')->nullable();
            $table->timestamps();

            // La numeración de ARCA es correlativa por punto de venta y tipo.
            // Este índice es lo que impide que dos emisiones simultáneas se
            // queden con el mismo número.
            $table->unique(['punto_venta', 'tipo', 'numero']);
            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');

        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'factura_activa', 'cuit', 'punto_venta', 'condicion_iva',
                'arca_certificado', 'arca_clave_privada', 'arca_ambiente',
            ]);
        });
    }
};
