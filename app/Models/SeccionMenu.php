<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cada ítem del menú de la tienda. El menú dejó de estar escrito en el código:
 * el negocio lo arma como quiere (nombre, emoji, a dónde lleva, orden) y puede
 * apagar lo que no usa sin perderlo.
 */
class SeccionMenu extends Model
{
    use HasFactory;

    protected $table = 'secciones_menu';

    protected $fillable = ['titulo', 'emoji', 'destino_tipo', 'destino_valor', 'orden', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Destinos posibles, en el orden en que se le ofrecen al dueño. Los cinco
     * primeros son las pantallas del motor; los últimos apuntan a algo suyo.
     */
    public const DESTINOS = [
        'home' => 'Inicio',
        'categorias' => 'Todas las categorías',
        'marcas' => 'Todas las marcas',
        'combos' => 'Combos',
        'nuevos' => 'Productos nuevos',
        'ofertas' => 'Ofertas',
        'categoria' => 'Una categoría en particular',
        'marca' => 'Una marca en particular',
        'pagina' => 'Una página mía',
        'url' => 'Un link externo',
    ];

    /** Los destinos que necesitan que además se elija a qué apuntan. */
    public const DESTINOS_CON_VALOR = ['categoria', 'marca', 'pagina', 'url'];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('id');
    }

    /**
     * La dirección final del ítem. Null cuando quedó apuntando a algo que ya
     * no existe (una categoría borrada): el menú lo saltea en vez de mostrar
     * un link roto.
     */
    public function getUrlAttribute(): ?string
    {
        return match ($this->destino_tipo) {
            'home' => route('home'),
            'categorias' => route('productos.index', ['vista' => 'categorias']),
            'marcas' => route('productos.index', ['vista' => 'marcas']),
            'combos' => route('combos.index'),
            'nuevos' => route('nuevos'),
            'ofertas' => route('ofertas'),
            'categoria' => $this->destino_valor
                ? route('productos.index', ['vista' => 'categorias', 'categoria' => $this->destino_valor])
                : null,
            'marca' => $this->destino_valor
                ? route('productos.index', ['vista' => 'marcas', 'marca' => $this->destino_valor])
                : null,
            'pagina' => $this->destino_valor ? route('paginas.show', $this->destino_valor) : null,
            'url' => $this->destino_valor ?: null,
            default => null,
        };
    }
}
