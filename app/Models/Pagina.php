<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Páginas de contenido que el negocio escribe desde el panel ("Nosotros",
 * "Cómo comprar", "Preguntas frecuentes"). Se listan solas en el pie de la
 * tienda: no hay ninguna fija en el código.
 */
class Pagina extends Model
{
    use HasFactory;

    protected $table = 'paginas';

    protected $fillable = ['titulo', 'slug', 'contenido', 'orden', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pagina $pagina) {
            if (empty($pagina->slug)) {
                $pagina->slug = self::slugLibre(Str::slug($pagina->titulo) ?: 'pagina', $pagina->id);
            }
        });
    }

    /**
     * El slug va en la URL y es único: si el negocio crea dos páginas con el
     * mismo título, la segunda pasa a "-2" en vez de romper con un error de
     * base que el dueño no sabría interpretar.
     */
    private static function slugLibre(string $base, ?int $ignorarId): string
    {
        $slug = $base;
        $sufijo = 1;

        while (static::where('slug', $slug)->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))->exists()) {
            $sufijo++;
            $slug = "{$base}-{$sufijo}";
        }

        return $slug;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('titulo');
    }
}
