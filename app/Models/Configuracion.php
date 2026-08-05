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

    /**
     * El aspecto de la tienda. Cada clave es una carpeta en
     * resources/js/Plantillas: adentro va lo que esa plantilla pisa (el marco,
     * la tarjeta de producto, las páginas que quiera) y todo lo demás lo hereda
     * de resources/js/Pages. Agregar una plantilla nueva es agregar una carpeta
     * y una entrada acá.
     *
     * Son pocas y elegidas a propósito: con libertad total la mayoría de los
     * negocios termina con una tienda fea, y esa tienda lleva nuestro nombre.
     */
    public const PLANTILLAS = [
        'catalogo' => [
            'nombre' => 'Catálogo',
            'descripcion' => 'Menú en una barra lateral fija y grilla densa, con el precio y el stock siempre a la vista. Para catálogos grandes, donde el cliente entra buscando un producto puntual.',
            'rubros' => 'Distribuidora, ferretería, dietética, repuestos, librería.',
        ],
        'vidriera' => [
            'nombre' => 'Vidriera',
            'descripcion' => 'Sin barra lateral: el menú va arriba y la foto ocupa casi toda la tarjeta, en dos o tres columnas. Para cuando lo que vende es cómo se ve el producto.',
            'rubros' => 'Ropa, calzado, deco, cosmética, regalería.',
        ],
        'mostrador' => [
            'nombre' => 'Mostrador',
            'descripcion' => 'Una lista compacta: miniatura, nombre, precio y los botones + y − en la misma fila. Pensada para el cliente que ya sabe qué quiere y carga el pedido desde el celular.',
            'rubros' => 'Mayorista con clientes que recompran, corralón, proveeduría.',
        ],
        'carta' => [
            'nombre' => 'Carta',
            'descripcion' => 'Solapas por categoría y una línea por producto, como una carta de papel. Para catálogos chicos donde la foto no es lo importante.',
            'rubros' => 'Rotisería, panadería, gastronomía, viveros.',
        ],
    ];

    /**
     * Las claves son las mismas que usa fonts.bunny.net (el espejo de Google
     * Fonts que ya sirve la tienda), así que la clave arma sola la dirección.
     */
    public const TIPOGRAFIAS = [
        'inter' => ['nombre' => 'Inter — neutra y moderna', 'familia' => 'Inter', 'pesos' => '300,400,500,600,700'],
        'poppins' => ['nombre' => 'Poppins — redondeada y amable', 'familia' => 'Poppins', 'pesos' => '300,400,500,600,700'],
        'lora' => ['nombre' => 'Lora — clásica, con serifas', 'familia' => 'Lora', 'pesos' => '400,500,600,700'],
        'archivo' => ['nombre' => 'Archivo — compacta y firme', 'familia' => 'Archivo', 'pesos' => '300,400,500,600,700'],
    ];

    protected $fillable = [
        'envio_gratis_desde', 'controlar_stock',
        'nombre_negocio', 'eslogan', 'descripcion', 'direccion', 'ciudad',
        'telefono', 'whatsapp', 'instagram', 'logo', 'medios_pago',
        'color_acento', 'marca_destacada_id', 'email_avisos', 'pedido_minimo_mayorista',
        'mostrar_filtros_alimentos', 'mostrar_lista_precios', 'mostrar_combos', 'mostrar_ofertas',
        'plantilla', 'tipografia',
    ];

    protected $casts = [
        'envio_gratis_desde' => 'decimal:2',
        'pedido_minimo_mayorista' => 'decimal:2',
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

    /**
     * Monto mínimo de pedido que le corre a este cliente. El mínimo es solo
     * para los que compran por mayor: al particular que compra una unidad no
     * se le puede exigir un piso pensado para reventa. 0 = sin mínimo.
     */
    public function pedidoMinimoPara(?User $user): float
    {
        return $user?->tipo_cliente === 'negocio'
            ? (float) $this->pedido_minimo_mayorista
            : 0.0;
    }

    /** "Efectivo, Transferencia" -> ['Efectivo', 'Transferencia'] */
    public function mediosPago(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->medios_pago))));
    }

    /**
     * La plantilla elegida en el panel. Si quedó guardada una que ya no existe
     * (se renombró una carpeta, se restauró una base vieja), vale más una
     * tienda con el aspecto default que una tienda en blanco.
     */
    public function plantilla(): string
    {
        return isset(self::PLANTILLAS[$this->plantilla]) ? $this->plantilla : 'catalogo';
    }

    /** Ídem con la tipografía. */
    public function tipografia(): string
    {
        return isset(self::TIPOGRAFIAS[$this->tipografia]) ? $this->tipografia : 'inter';
    }

    /** El nombre de la familia, para la variable CSS que lee Tailwind. */
    public function fuenteFamilia(): string
    {
        return self::TIPOGRAFIAS[$this->tipografia()]['familia'];
    }

    /** La hoja de estilos de la fuente, servida por fonts.bunny.net. */
    public function fuenteUrl(): string
    {
        $clave = $this->tipografia();

        return 'https://fonts.bunny.net/css?family='.$clave.':'.self::TIPOGRAFIAS[$clave]['pesos'].'&display=swap';
    }

    /**
     * Qué archivo precargar para esta pantalla: el de la plantilla si lo pisó,
     * si no el del motor. Sin esto el blade precargaría siempre el del motor y
     * el navegador terminaría bajando dos veces la misma pantalla.
     */
    public function vistaDeLaPagina(string $componente): string
    {
        $propia = 'resources/js/Plantillas/'.$this->plantilla().'/Pages/'.$componente.'.vue';

        return file_exists(base_path($propia)) ? $propia : 'resources/js/Pages/'.$componente.'.vue';
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
                    // Sin esto queda en null y la pantalla de Configuración de
                    // una tienda recién instalada no deja guardar hasta que el
                    // dueño escriba un 0 a mano en "pedido mínimo".
                    'pedido_minimo_mayorista' => 0,
                    'controlar_stock' => true,
                    // Explícitos y no solo defaults de columna: firstOrCreate
                    // devuelve el modelo en memoria, sin los defaults de la DB.
                    'nombre_negocio' => 'Mi Tienda',
                    'plantilla' => 'catalogo',
                    'tipografia' => 'inter',
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
