<?php

namespace App\Models;

use App\Observers\PedidoItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PedidoItemObserver::class)]
class PedidoItem extends Model
{
    protected $fillable = ['pedido_id', 'presentacion_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Los importes tampoco viajan al navegador del operador. Es el mismo caso
     * que los costos: escondidos en la pantalla, pero presentes en el estado
     * del formulario. El servidor los rearma igual (ver
     * PedidoResource::precioDeLaBase).
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $data = parent::attributesToArray();

        if ($this->esOperador()) {
            unset($data['precio_unitario'], $data['subtotal']);
        }

        return $data;
    }

    private function esOperador(): bool
    {
        $usuario = auth()->user();

        return ($usuario?->isOperador() ?? false) && ! $usuario->isAdmin();
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<Presentacion, $this>
     */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }
}
