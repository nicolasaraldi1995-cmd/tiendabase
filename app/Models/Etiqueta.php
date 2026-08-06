<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una etiqueta del negocio: "Sin TACC", "Inoxidable", "Bajo pedido", "Planta de
 * interior". Reemplaza a las tres columnas fijas de alimentos que traía el
 * motor, que solo le servían a un rubro.
 *
 * Tiene dos poderes, y son los que antes estaban escritos en el código:
 *
 *   en_filtros  aparece como filtro en el menú de la tienda
 *   aviso       si el pedido lleva un producto con esta etiqueta, el carrito
 *               muestra ese texto y ofrece sacarlos
 *
 * El aviso es lo que generaliza la cadena de frío: donde una dietética pone
 * "Consultá disponibilidad para tu zona", una ferretería pone "Bajo pedido:
 * consultá demora antes de confirmar".
 */
class Etiqueta extends Model
{
    protected $table = 'etiquetas';

    protected $fillable = ['nombre', 'color', 'orden', 'activo', 'en_filtros', 'aviso'];

    protected $casts = [
        'activo' => 'boolean',
        'en_filtros' => 'boolean',
        'orden' => 'integer',
    ];

    /** @return BelongsToMany<Producto, $this> */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'etiqueta_producto');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }

    /** Las que el negocio quiere ver como filtro en el menú. */
    public function scopeEnFiltros($query)
    {
        return $query->activos()->where('en_filtros', true);
    }

    /** Las que hacen aparecer un aviso en el carrito. */
    public function scopeConAviso($query)
    {
        return $query->activos()->whereNotNull('aviso')->where('aviso', '!=', '');
    }

    /**
     * Lo que necesita la tienda para dibujarla. Null en el color significa "usá
     * el color principal del negocio", así una etiqueta sin color elegido
     * acompaña el resto del diseño en vez de desentonar.
     *
     * @return array<string, mixed>
     */
    public function paraLaTienda(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'color' => $this->color,
        ];
    }
}
