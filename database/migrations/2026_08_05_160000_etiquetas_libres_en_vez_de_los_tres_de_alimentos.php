<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Sin TACC", "Frío" y "Congelado" eran tres columnas fijas en `productos`: una
 * ferretería tenía tres casillas que no le sirven y ninguna que sí. Pasan a ser
 * etiquetas que carga el negocio, con dos poderes que antes estaban escritos en
 * el código:
 *
 *   en_filtros  la etiqueta aparece como filtro en el menú de la tienda
 *   aviso       muestra ese texto en el carrito si el pedido la lleva
 *
 * El aviso es lo que generaliza la cadena de frío: una ferretería puede poner
 * "Bajo pedido: consultá demora antes de confirmar" y un vivero "Se retira en
 * persona".
 *
 * Las tres de antes se convierten en etiquetas reales SOLO si el negocio las
 * estaba usando: una tienda que no vende comida no hereda etiquetas de comida.
 */
return new class extends Migration
{
    private const AVISO_FRIO = 'Consultá disponibilidad para tu zona antes de confirmar.';

    public function up(): void
    {
        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            // Null = usa el color principal de la tienda.
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->boolean('en_filtros')->default(true);
            $table->string('aviso')->nullable();
            $table->timestamps();
        });

        Schema::create('etiqueta_producto', function (Blueprint $table) {
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->primary(['producto_id', 'etiqueta_id']);
        });

        $this->traerLasTresDeAntes();

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['sin_tacc', 'frio', 'congelado']);
        });

        // El aviso ya no es solo de fríos: el cliente al que no se le muestra
        // ninguno tampoco depende de un rubro.
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('recibe_frio_congelado', 'omite_avisos');
        });

        // El interruptor global de "filtros de alimentos" queda sin sentido:
        // ahora cada etiqueta decide sola si sale como filtro.
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn('mostrar_filtros_alimentos');
            // Y de paso: no todos los negocios reparten. Con esto apagado el
            // checkout ofrece solo retiro, en vez de aceptar un envío sin dónde.
            $table->boolean('hace_envios')->default(true);
        });
    }

    /**
     * Cada columna vieja se convierte en etiqueta solo si algún producto la
     * tenía marcada, y se le cuelgan esos productos.
     */
    private function traerLasTresDeAntes(): void
    {
        // Lo que el negocio tenía elegido para los filtros del menú.
        $mostrarFiltros = (bool) (DB::table('configuraciones')->value('mostrar_filtros_alimentos') ?? true);

        $mapa = [
            'sin_tacc' => ['nombre' => 'Sin TACC', 'color' => null, 'aviso' => null],
            'frio' => ['nombre' => 'Frío', 'color' => '#0ea5e9', 'aviso' => self::AVISO_FRIO],
            'congelado' => ['nombre' => 'Congelado', 'color' => '#2563eb', 'aviso' => self::AVISO_FRIO],
        ];

        $orden = 0;

        foreach ($mapa as $columna => $datos) {
            $orden++;
            $productos = DB::table('productos')->where($columna, true)->pluck('id');

            if ($productos->isEmpty()) {
                continue;
            }

            $etiquetaId = DB::table('etiquetas')->insertGetId([
                'nombre' => $datos['nombre'],
                'color' => $datos['color'],
                'orden' => $orden * 10,
                'activo' => true,
                'en_filtros' => $mostrarFiltros,
                'aviso' => $datos['aviso'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('etiqueta_producto')->insert(
                $productos->map(fn ($id) => ['producto_id' => $id, 'etiqueta_id' => $etiquetaId])->all()
            );
        }
    }

    public function down(): void
    {
        // Las columnas vuelven pero vacías: los productos etiquetados a mano
        // después de migrar no tienen dónde volver, y adivinar sería peor.
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('sin_tacc')->default(false);
            $table->boolean('frio')->default(false);
            $table->boolean('congelado')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('omite_avisos', 'recibe_frio_congelado');
        });

        Schema::table('configuraciones', function (Blueprint $table) {
            $table->boolean('mostrar_filtros_alimentos')->default(true);
            $table->dropColumn('hace_envios');
        });

        Schema::dropIfExists('etiqueta_producto');
        Schema::dropIfExists('etiquetas');
    }
};
