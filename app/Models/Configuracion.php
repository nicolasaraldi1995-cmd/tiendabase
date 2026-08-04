<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Configuracion extends Model
{
    use HasMediaUrl;

    protected $table = 'configuraciones';

    protected $fillable = [
        'envio_gratis_desde', 'controlar_stock',
        'nombre_negocio', 'eslogan', 'descripcion', 'direccion', 'ciudad',
        'telefono', 'whatsapp', 'instagram', 'logo', 'medios_pago',
        'color_acento', 'marca_destacada_id', 'email_avisos',
        'mostrar_filtros_alimentos', 'mostrar_lista_precios', 'mostrar_combos', 'mostrar_ofertas',
    ];

    protected $casts = [
        'envio_gratis_desde' => 'decimal:2',
        'controlar_stock' => 'boolean',
        'mostrar_filtros_alimentos' => 'boolean',
        'mostrar_lista_precios' => 'boolean',
        'mostrar_combos' => 'boolean',
        'mostrar_ofertas' => 'boolean',
    ];

    /** @return BelongsTo<Marca, $this> */
    public function marcaDestacada(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_destacada_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->logo);
    }

    /** "Efectivo, Transferencia" -> ['Efectivo', 'Transferencia'] */
    public function mediosPago(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->medios_pago))));
    }

    /** El color de acento elegido en el panel, o el default del motor. */
    public function colorAcento(): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $this->color_acento)
            ? $this->color_acento
            : '#5ca8cc';
    }

    /** Variante oscura (títulos, hover de links). */
    public function colorAcentoDim(): string
    {
        return $this->oscurecer($this->colorAcento(), 0.70);
    }

    /** Variante apenas más oscura (hover de botones). */
    public function colorAcentoBright(): string
    {
        return $this->oscurecer($this->colorAcento(), 0.90);
    }

    /** "92,168,204" — para usar dentro de rgba(...) en los blades. */
    public function colorAcentoRgb(): string
    {
        return implode(',', sscanf($this->colorAcento(), '#%02x%02x%02x'));
    }

    /** Ídem, de la variante oscura. */
    public function colorAcentoDimRgb(): string
    {
        return implode(',', sscanf($this->colorAcentoDim(), '#%02x%02x%02x'));
    }

    /**
     * Variables para inyectar en :root (formato "R G B", el que esperan las
     * clases de Tailwind). Null si el negocio no eligió color: rigen los
     * defaults de app.css.
     */
    public function coloresAcentoVars(): ?array
    {
        if (! preg_match('/^#[0-9a-f]{6}$/i', (string) $this->color_acento)) {
            return null;
        }

        return [
            '--accent' => $this->tripleta($this->colorAcento()),
            '--accent-dim' => $this->tripleta($this->colorAcentoDim()),
            '--accent-bright' => $this->tripleta($this->colorAcentoBright()),
        ];
    }

    private function tripleta(string $hex): string
    {
        return implode(' ', sscanf($hex, '#%02x%02x%02x'));
    }

    private function oscurecer(string $hex, float $factor): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) round($r * $factor), (int) round($g * $factor), (int) round($b * $factor));
    }

    /**
     * El logo como data URI, para embeberlo en PDFs (dompdf) y en la lista de
     * precios HTML autocontenida que se manda por WhatsApp. Null si no hay logo.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        if (! $disk->exists($this->logo)) {
            return null;
        }

        $contenido = (string) $disk->get($this->logo);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contenido);
    }

    /**
     * Memoizado en el contenedor (una vez por request/app lifetime): se llama
     * una vez por ítem de pedido al descontar stock, y no cambia dentro de un
     * mismo request. Usar el contenedor en vez de una propiedad estática
     * evita que el valor quede pegado entre tests, que corren en un solo
     * proceso PHP largo.
     */
    public static function actual(): self
    {
        if (! app()->bound(static::class.'@actual')) {
            app()->instance(
                static::class.'@actual',
                static::firstOrCreate(['id' => 1], [
                    'envio_gratis_desde' => 0,
                    'controlar_stock' => true,
                    // Explícitos y no solo defaults de columna: firstOrCreate
                    // devuelve el modelo en memoria, sin los defaults de la DB.
                    'nombre_negocio' => 'Mi Tienda',
                    'mostrar_filtros_alimentos' => true,
                    'mostrar_lista_precios' => true,
                    'mostrar_combos' => true,
                    'mostrar_ofertas' => true,
                ])
            );
        }

        return app(static::class.'@actual');
    }
}
