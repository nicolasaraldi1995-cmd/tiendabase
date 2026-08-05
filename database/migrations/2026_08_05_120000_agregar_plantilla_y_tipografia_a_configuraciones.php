<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El aspecto de la tienda deja de estar clavado en el código: el negocio elige
 * plantilla y tipografía desde el panel. Los defaults son los de siempre, así
 * que una tienda ya instalada no cambia de aspecto al migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->string('plantilla', 30)->default('catalogo');
            $table->string('tipografia', 30)->default('inter');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['plantilla', 'tipografia']);
        });
    }
};
