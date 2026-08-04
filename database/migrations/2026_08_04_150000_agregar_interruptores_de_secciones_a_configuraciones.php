<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->boolean('mostrar_filtros_alimentos')->default(true);
            $table->boolean('mostrar_lista_precios')->default(true);
            $table->boolean('mostrar_combos')->default(true);
            $table->boolean('mostrar_ofertas')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_filtros_alimentos', 'mostrar_lista_precios',
                'mostrar_combos', 'mostrar_ofertas',
            ]);
        });
    }
};
