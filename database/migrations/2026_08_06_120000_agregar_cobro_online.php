<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro online con MercadoPago. Una sola migración porque es un solo cambio:
 * el negocio elige si cobra online, con qué credenciales, y cada pago que
 * entra por esa vía queda atado al id de MercadoPago que lo originó.
 *
 * `pedidos.estado` NO se toca: es un string con índice, no un enum, así que
 * el estado nuevo ('awaiting_payment') sale del modelo y no del esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            // 'coordinar' de default: una tienda ya instalada que corra esta
            // migración sigue comportándose exactamente igual que antes.
            $table->string('modo_cobro')->default('coordinar');

            // Guardados con el cast `encrypted`, así que entran bastante más
            // largos que el valor original: text y no string.
            //
            // Son dos credenciales distintas y las dos son propias de cada
            // negocio: el token autoriza a crear cobros en SU cuenta, y el
            // secreto es con el que se verifica que una notificación venga
            // de verdad de MercadoPago y no de cualquiera que adivine la URL.
            $table->text('mp_access_token')->nullable();
            $table->text('mp_webhook_secret')->nullable();
        });

        Schema::table('pagos', function (Blueprint $table) {
            // El único que impide cobrar dos veces el mismo pago. MercadoPago
            // reintenta la notificación (a los 15 min, 30 min, 6 h y 48 h), así
            // que el mismo pago llega varias veces por diseño: la garantía tiene
            // que estar en la base y no en que el código se acuerde de chequear.
            //
            // Nullable y único a la vez es correcto: los pagos que carga el
            // negocio a mano no tienen id de MercadoPago, y varios NULL conviven
            // sin romper el índice.
            $table->string('mp_payment_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['modo_cobro', 'mp_access_token', 'mp_webhook_secret']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['mp_payment_id']);
            $table->dropColumn('mp_payment_id');
        });
    }
};
