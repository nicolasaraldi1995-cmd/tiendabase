<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private const TIPOS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        // Windows guarda los JPEG como .jfif y los celulares sacan .avif.
        // Sin estas dos el navegador recibía octet-stream y no mostraba nada.
        'jfif' => 'image/jpeg',
        'avif' => 'image/avif',
    ];

    /**
     * Sirve los archivos del disco configurado (S3 u otro) a través del
     * propio dominio de la app, en vez de que el navegador pida el dominio
     * público del bucket directamente -- ese dominio devuelve 503 en el
     * embed cruzado de <img> aunque el archivo esté perfecto (confirmado con
     * curl y con navegación directa, ambos siempre 200).
     */
    /**
     * Las únicas carpetas del disco que son de cara al público: todas de
     * imágenes. Se listan a mano, en vez de prohibir "imports", para que una
     * carpeta interna nueva nazca cerrada y no abierta.
     */
    private const CARPETAS_PUBLICAS = [
        'banners',
        'branding',
        'categorias',
        'combos',
        'marcas',
        'presentaciones',
        'productos',
    ];

    public function show(string $path): Response
    {
        // Sin esto se bajaba cualquier archivo del disco sin estar logueado,
        // incluidas las planillas de imports, que llevan costos y márgenes.
        abort_unless($this->esPublico($path), 404);

        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        abort_unless($disk->exists($path), 404);

        $contenido = $disk->get($path) ?? '';

        return response($contenido)
            ->header('Content-Type', $this->tipoDe($path, $contenido))
            ->header('Cache-Control', 'public, max-age=604800')
            // Un SVG es texto y puede traer <script> adentro: servido desde el
            // dominio propio, ese script correría con la sesión de quien abra
            // la imagen. Con sandbox no corre nada.
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    private function esPublico(string $path): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        $carpeta = strtok($path, '/');

        return $carpeta !== false
            && in_array($carpeta, self::CARPETAS_PUBLICAS, true)
            && str_contains($path, '/');
    }

    /**
     * Manda el contenido, no el nombre: el servidor reprocesa las imágenes de
     * banners y marcas y las deja en JPEG sin renombrar el archivo, así que un
     * ".png" puede tener un JPEG adentro. La extensión queda de respaldo para
     * lo que finfo no reconoce, como los SVG.
     */
    private function tipoDe(string $path, string $contenido): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'svg') {
            return self::TIPOS['svg'];
        }

        $detectado = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);

        if (is_string($detectado) && str_starts_with($detectado, 'image/')) {
            return $detectado;
        }

        return self::TIPOS[$extension] ?? 'application/octet-stream';
    }
}
