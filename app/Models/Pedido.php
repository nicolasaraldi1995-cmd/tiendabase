<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'estado', 'total', 'datos_cliente'];

    protected $casts = [
        'total' => 'decimal:2',
        'datos_cliente' => 'array',
    ];

    const ESTADOS = [
        // Antes de 'pending' porque va antes en el circuito: el pedido existe y
        // ya reservó stock, pero todavía no se pagó. Solo lo pone el checkout
        // cuando el cliente elige pagar online, y solo sale de acá por el
        // webhook de MercadoPago o por vencimiento. Ver ESTADOS_QUE_SE_ELIGEN.
        'awaiting_payment' => 'Esperando pago',
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'En preparación',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'canceled' => 'Cancelado',
    ];

    /**
     * Cuánto se le retiene el stock a alguien que está pagando. Pasado esto sin
     * pagar, el pedido se cancela solo y las unidades vuelven al catálogo.
     *
     * Media hora alcanza de sobra para tarjeta o dinero en cuenta, y no deja el
     * catálogo trabado si el cliente abandona. OJO: pagar en efectivo por
     * Rapipago o Pago Fácil tarda DÍAS, no minutos — si alguna vez se habilita
     * ese medio, estos pedidos se estarían cancelando antes de que el cliente
     * llegue al kiosco.
     */
    public const MINUTOS_PARA_PAGAR = 30;

    /**
     * Los que el negocio puede poner a mano desde el panel. 'awaiting_payment'
     * queda afuera a propósito: sin una preferencia de pago viva detrás, un
     * pedido puesto ahí a mano se quedaría esperando un pago que nadie pidió y
     * lo terminaría cancelando el vencimiento. Sigue en ESTADOS para que se
     * muestre con su nombre y se pueda filtrar.
     *
     * @return array<string, string>
     */
    public static function estadosQueSeEligen(): array
    {
        return array_diff_key(self::ESTADOS, ['awaiting_payment' => null]);
    }

    /** Reservó stock pero todavía no se pagó: no se toca hasta que se resuelva. */
    public function esperaPago(): bool
    {
        return $this->estado === 'awaiting_payment';
    }

    public function esEditable(): bool
    {
        return $this->estado === 'pending';
    }

    /**
     * El operador arma y prepara pedidos, pero no ve cuánta plata mueven.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();
        $usuario = auth()->user();

        if (($usuario?->isOperador() ?? false) && ! $usuario->isAdmin()) {
            unset($data['total']);
        }

        return $data;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PedidoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function getTotalPagadoAttribute(): float
    {
        return (float) $this->pagos()->sum('monto');
    }

    public function getSaldoAttribute(): float
    {
        return (float) $this->total - $this->total_pagado;
    }

    public function recalcularTotal(): void
    {
        $this->update(['total' => $this->items()->sum('subtotal')]);
    }

    /**
     * Devuelve al stock las cantidades reservadas por este pedido.
     * Se usa al cancelar un pedido; los items no se borran, así que
     * PedidoItemObserver no dispara automáticamente en este caso.
     */
    public function restaurarStock(): void
    {
        DB::transaction(function () {
            foreach ($this->items as $item) {
                Presentacion::whereKey($item->presentacion_id)
                    ->lockForUpdate()
                    ->increment('stock', $item->cantidad);
            }
        });
    }
}
