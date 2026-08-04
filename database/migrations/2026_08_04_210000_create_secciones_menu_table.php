<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones_menu', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('emoji', 16)->nullable();
            $table->string('destino_tipo', 30);
            $table->string('destino_valor')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $this->sembrarMenuActual();
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones_menu');
    }

    /**
     * El menú que hasta ahora estaba escrito en el código pasa a ser datos. Se
     * siembra igual a como se veía (respetando los interruptores de combos y
     * ofertas, y la marca destacada) para que ninguna tienda ya instalada
     * cambie de aspecto al actualizar.
     */
    private function sembrarMenuActual(): void
    {
        $config = DB::table('configuraciones')->find(1);

        $items = [
            ['titulo' => 'Inicio', 'emoji' => '🏠', 'destino_tipo' => 'home', 'activo' => true],
            ['titulo' => 'Categorías', 'emoji' => '🗂️', 'destino_tipo' => 'categorias', 'activo' => true],
            ['titulo' => 'Marcas', 'emoji' => '🏷️', 'destino_tipo' => 'marcas', 'activo' => true],
            ['titulo' => 'Combos', 'emoji' => '📦', 'destino_tipo' => 'combos', 'activo' => (bool) ($config->mostrar_combos ?? true)],
            ['titulo' => 'Nuevos', 'emoji' => '✨', 'destino_tipo' => 'nuevos', 'activo' => true],
        ];

        if ($config && $config->marca_destacada_id) {
            $marca = DB::table('marcas')->find($config->marca_destacada_id);

            if ($marca) {
                $items[] = [
                    'titulo' => $marca->nombre,
                    'emoji' => '⭐',
                    'destino_tipo' => 'marca',
                    'destino_valor' => (string) $marca->id,
                    'activo' => true,
                ];
            }
        }

        $items[] = ['titulo' => 'Ofertas', 'emoji' => '🔥', 'destino_tipo' => 'ofertas', 'activo' => (bool) ($config->mostrar_ofertas ?? true)];

        $ahora = now();

        DB::table('secciones_menu')->insert(array_map(fn (array $item, int $i) => $item + [
            'destino_valor' => null,
            'orden' => ($i + 1) * 10,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $items, array_keys($items)));
    }
};
