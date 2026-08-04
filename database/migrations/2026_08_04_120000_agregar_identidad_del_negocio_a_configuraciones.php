<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->string('nombre_negocio')->default('Mi Tienda');
            $table->string('eslogan')->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('instagram')->nullable();
            $table->string('logo')->nullable();
            $table->string('medios_pago')->nullable();
            $table->foreignId('marca_destacada_id')->nullable()->constrained('marcas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marca_destacada_id');
            $table->dropColumn([
                'nombre_negocio', 'eslogan', 'descripcion', 'direccion', 'ciudad',
                'telefono', 'whatsapp', 'instagram', 'logo', 'medios_pago',
            ]);
        });
    }
};
