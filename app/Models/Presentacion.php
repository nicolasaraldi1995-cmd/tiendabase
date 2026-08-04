<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El producto usa borrado lógico, así que la relación puede venir en null
 * aunque la columna sea obligatoria: por eso el código la lee con ?->.
 *
 * @property-read Producto|null $producto
 */
class Presentacion extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $table = 'presentaciones';

    protected $fillable = [
        'producto_id', 'unidad', 'sku', 'imagen', 'precio', 'stock', 'activo',
        'precio_mayorista', 'cantidad_mayorista',
        'oferta_porcentaje', 'oferta_precio', 'oferta_inicio', 'oferta_fin',
        'precio_costo', 'descuento_porcentaje', 'margen_porcentaje', 'iva',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_mayorista' => 'decimal:2',
        'cantidad_mayorista' => 'integer',
        'precio_costo' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'margen_porcentaje' => 'decimal:2',
        'iva' => 'boolean',
        'oferta_porcentaje' => 'decimal:2',
        'oferta_precio' => 'decimal:2',
        'oferta_inicio' => 'date',
        'oferta_fin' => 'date',
        'activo' => 'boolean',
        'stock' => 'integer',
    ];

    /**
     * Recorta lo que sale del modelo al serializarse (Inertia manda estos datos
     * al navegador, donde cualquiera los puede leer: esconderlos solo en el
     * diseño no alcanza).
     *
     * - costo/descuento/margen son datos internos del negocio y nunca deberían
     *   llegar al sitio público (antes viajaban a cualquier visitante).
     * - los precios solo se muestran a clientes con cuenta.
     *
     * El panel admin queda intacto: sus formularios necesitan estos campos.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        unset($data['precio_costo'], $data['descuento_porcentaje'], $data['margen_porcentaje']);

        if (auth()->guest()) {
            unset(
                $data['precio'], $data['oferta_precio'], $data['oferta_porcentaje'],
                $data['precio_mayorista'], $data['cantidad_mayorista'],
            );

            return $data;
        }

        // El precio que ve el cliente es el que va a pagar: cuando el mayorista
        // le gana a la oferta viaja ya resuelto y sin los datos de oferta, para
        // que la tienda no vuelva a descontar sobre un precio ya descontado.
        $usuario = auth()->user();
        $mayorista = $this->precioMayoristaAplicable($usuario);

        if ($mayorista !== null && $mayorista <= $this->precio_final) {
            $data['precio'] = number_format($mayorista, 2, '.', '');
            $data['oferta_precio'] = null;
            $data['oferta_porcentaje'] = null;
        }

        // El precio por mayor no se publica como dato suelto: solo se ofrece
        // como "llevando N te sale $X", que es lo que el cliente puede usar.
        unset($data['precio_mayorista']);
        $data['mayorista_desde'] = $this->mejorPrecioPorCantidad($usuario);

        return $data;
    }

    /**
     * Precio que realmente paga este cliente por esta cantidad: la fuente de
     * verdad de todo el sistema (carrito, checkout, pedidos y panel).
     *
     * El precio por mayor y la oferta no se encadenan: el cliente paga el
     * menor de los dos, nunca los dos descuentos aplicados uno sobre otro.
     */
    public function precioPara(?User $user, int $cantidad = 1): float
    {
        $mayorista = $this->precioMayoristaAplicable($user, $cantidad);

        return $mayorista === null
            ? $this->precio_final
            : min($this->precio_final, $mayorista);
    }

    /**
     * Con un solo precio cargado se resuelven los dos casos del mostrador:
     * el cliente es un negocio (paga por mayor siempre), o cualquiera se
     * lleva la cantidad mínima por mayor. Null = no hay mayorista que aplicar.
     */
    public function precioMayoristaAplicable(?User $user, int $cantidad = 1): ?float
    {
        if ($this->precio_mayorista === null) {
            return null;
        }

        $esNegocio = $user?->tipo_cliente === 'negocio';
        $llegaALaCantidad = $this->cantidad_mayorista > 0 && $cantidad >= $this->cantidad_mayorista;

        return $esNegocio || $llegaALaCantidad ? (float) $this->precio_mayorista : null;
    }

    /**
     * Si al cliente le conviene llevar más, cuánto y a qué precio. Viaja
     * calculado para que la tienda lo muestre sin repetir la regla de
     * precios en el navegador. Null si no hay nada mejor que ofrecerle.
     */
    public function mejorPrecioPorCantidad(?User $user): ?array
    {
        if ($this->precio_mayorista === null || ! $this->cantidad_mayorista) {
            return null;
        }

        if ($this->precioPara($user) <= (float) $this->precio_mayorista) {
            return null;
        }

        return [
            'cantidad' => $this->cantidad_mayorista,
            'precio' => (float) $this->precio_mayorista,
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeEnOferta($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('oferta_porcentaje')
                ->orWhereNotNull('oferta_precio');
        })->where(function ($q) {
            $q->whereNull('oferta_inicio')->orWhere('oferta_inicio', '<=', now());
        })->where(function ($q) {
            // oferta_fin es una columna DATE (medianoche); comparar contra la fecha
            // sola (no now() completo) para que la oferta siga activa todo ese día.
            $q->whereNull('oferta_fin')->orWhere('oferta_fin', '>=', now()->toDateString());
        });
    }

    public function getPrecioFinalAttribute(): float
    {
        if ($this->estaEnOferta()) {
            if ($this->oferta_precio) {
                return (float) $this->oferta_precio;
            }
            if ($this->oferta_porcentaje) {
                return round($this->precio * (1 - $this->oferta_porcentaje / 100), 2);
            }
        }

        return (float) $this->precio;
    }

    public function estaEnOferta(): bool
    {
        if (! $this->oferta_porcentaje && ! $this->oferta_precio) {
            return false;
        }
        if ($this->oferta_inicio && $this->oferta_inicio->isFuture()) {
            return false;
        }
        if ($this->oferta_fin && $this->oferta_fin->copy()->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
