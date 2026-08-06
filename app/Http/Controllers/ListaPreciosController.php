<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ListaPreciosController extends Controller
{
    public function index()
    {
        $categorias = Categoria::activos()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['marca', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Categoria $c) => $c->productos->isNotEmpty())
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'productos' => $c->productos->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'marca' => $p->marca->nombre ?? '—',
                    'etiquetas' => $p->etiquetas->where('activo', true)->map(fn ($e) => $e->paraLaTienda())->values()->all(),
                    'presentaciones' => $p->presentaciones->map(fn (Presentacion $pr) => [
                        'unidad' => $pr->unidad,
                        'precio' => (float) $pr->precio,
                        'precio_final' => $pr->precio_final,
                        'en_oferta' => $pr->estaEnOferta(),
                        'stock' => $pr->stock,
                    ]),
                ]),
            ])
            ->values();

        $marcas = Marca::activos()->orderBy('nombre')->pluck('nombre')->values();

        return Inertia::render('ListaPrecios', [
            'categorias' => $categorias,
            'marcas' => $marcas,
        ]);
    }

    /**
     * Lista de precios como un HTML autocontenido (estilos, script y logo
     * embebidos) pensada para mandar por WhatsApp: el cliente la abre en el
     * celular y funciona sin internet, con buscador y marcas desplegables.
     */
    public function html()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(90);

        $marcas = Marca::activos()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['categoria', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($m) => $m->productos->isNotEmpty())
            ->map(fn ($m) => [
                'nombre' => $m->nombre,
                'inicial' => mb_strtoupper(mb_substr($m->nombre, 0, 1)),
                'productos' => $m->productos
                    ->filter(fn ($p) => $p->presentaciones->isNotEmpty())
                    ->map(fn ($p) => [
                        'nombre' => $p->nombre,
                        'categoria' => $p->categoria?->nombre ?? 'Sin categoría',
                        'etiquetas' => $p->etiquetas->where('activo', true)->map(fn ($e) => $e->paraLaTienda())->values()->all(),
                        'presentaciones' => $p->presentaciones->map(fn ($pr) => [
                            // El id viaja en el archivo del pedido para que al
                            // cargarlo el cruce sea exacto y no por nombre.
                            'id' => $pr->id,
                            'unidad' => $pr->unidad,
                            'precio' => (float) $pr->precio,
                            'precio_final' => $pr->precio_final,
                            'en_oferta' => $pr->estaEnOferta(),
                        ])->values()->all(),
                    ])->values()->all(),
            ])
            ->filter(fn ($m) => $m['productos'] !== [])
            ->values();

        $negocio = Configuracion::actual();

        $html = view('lista-precios-html', [
            'marcas' => $marcas,
            'negocio' => $negocio,
            'logoData' => $negocio->logoDataUri(),
            'totalProductos' => $marcas->sum(fn ($m) => count($m['productos'])),
            'generado' => now(),
        ])->render();

        // Se devuelve como descarga en streaming a propósito: Livewire inyecta
        // su <script src="/livewire/livewire.js"> en cualquier respuesta HTML
        // común, y ese archivo no existe en el celular del cliente -- rompía
        // justamente lo que hace útil a esta lista, que ande sin internet.
        return response()->streamDownload(
            fn () => print ($html),
            Str::upper(Str::slug($negocio->nombre_negocio)).'-precios-'.now()->format('d-m-Y').'.html',
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    /**
     * La lista completa como planilla, con las mismas columnas que espera el
     * importador: se exporta, se editan los precios en Excel y se vuelve a
     * subir sin convertir nada.
     *
     * Sale en CSV con punto y coma y BOM, que es lo que abre bien el Excel en
     * español sin tener que elegir nada.
     */
    public function planilla()
    {
        $productos = Producto::activos()
            ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()->orderBy('unidad')])
            ->orderBy('nombre')
            ->get();

        $nombre = Str::slug(Configuracion::actual()->nombre_negocio).'-lista-precios-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($productos) {
            $salida = fopen('php://output', 'w');

            // BOM: sin esto Excel muestra mal los acentos.
            fwrite($salida, "\xEF\xBB\xBF");
            // En minúscula y con las columnas mayoristas: los mismos títulos
            // que la plantilla que se le entrega al negocio, así lo que sale
            // de acá se puede volver a subir tal cual.
            fputcsv($salida, ['nombre', 'marca', 'categoria', 'unidad', 'precio', 'precio_mayorista', 'cantidad_mayorista', 'stock'], ';');

            foreach ($productos as $producto) {
                foreach ($producto->presentaciones as $presentacion) {
                    fputcsv($salida, [
                        self::comoTexto($producto->nombre),
                        self::comoTexto($producto->marca->nombre ?? ''),
                        self::comoTexto($producto->categoria->nombre ?? ''),
                        self::comoTexto($presentacion->unidad),
                        number_format((float) $presentacion->precio, 2, ',', '.'),
                        $presentacion->precio_mayorista !== null ? number_format((float) $presentacion->precio_mayorista, 2, ',', '.') : '',
                        $presentacion->cantidad_mayorista ?: '',
                        $presentacion->stock,
                    ], ';');
                }
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Excel trata como fórmula toda celda que empiece con =, +, - o @. Un
     * producto llamado "=HYPERLINK(...)" se ejecutaba al abrir la planilla en
     * la máquina de quien la bajara. Con la comilla adelante lo muestra como
     * texto y no la ejecuta.
     */
    private static function comoTexto(?string $valor): string
    {
        $valor = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $valor) === 1 ? "'".$valor : $valor;
    }

    public function pdf()
    {
        // ponytail: ~2000 productos tarda ~28s y ~320MB en dompdf, por encima
        // de los límites default de PHP. Techo: si el catálogo crece bastante
        // más, esto deja de alcanzar y hay que sacar la generación del request
        // (job en cola + aviso cuando esté listo).
        ini_set('memory_limit', '512M');
        set_time_limit(90);

        $categorias = Categoria::activos()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['marca', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($c) => $c->productos->isNotEmpty());

        $totalProductos = $categorias->sum(fn ($c) => $c->productos->count());
        $totalPresentaciones = $categorias->sum(fn ($c) => $c->productos->sum(fn ($p) => $p->presentaciones->count()));

        return Pdf::loadView('pdf.lista-precios', compact('categorias', 'totalProductos', 'totalPresentaciones'))
            ->setPaper('a4', 'portrait')
            ->download(Str::upper(Str::slug(Configuracion::actual()->nombre_negocio)).'-lista-precios-'.now()->format('Y-m-d').'.pdf');
    }
}
