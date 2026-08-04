<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            $table->decimal('precio_mayorista', 10, 2)->nullable()->after('precio');
            $table->unsignedInteger('cantidad_mayorista')->nullable()->after('precio_mayorista');
        });
    }

    public function down(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            $table->dropColumn(['precio_mayorista', 'cantidad_mayorista']);
        });
    }
};
