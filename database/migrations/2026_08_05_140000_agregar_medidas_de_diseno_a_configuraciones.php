<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las medidas del marco (alto del logo, alto de la barra, ancho del menú
 * lateral y aire entre secciones) dejan de estar clavadas en las clases de cada
 * plantilla y pasan a ser variables CSS que el negocio elige desde el panel.
 *
 * Los defaults son exactamente los valores que tenían las clases, así que una
 * tienda ya instalada no se mueve un pixel al migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('logo_alto')->default(40);
            $table->unsignedSmallInteger('barra_alto')->default(64);
            $table->unsignedSmallInteger('menu_ancho')->default(240);
            $table->unsignedSmallInteger('menu_espacio')->default(2);
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['logo_alto', 'barra_alto', 'menu_ancho', 'menu_espacio']);
        });
    }
};
