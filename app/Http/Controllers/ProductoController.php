<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductoController extends Controller
{
    /**
     * Cuántos resultados devuelve una búsqueda. La pantalla los agrupa por
     * categoría y no pagina, así que sin tope una búsqueda amplia mandaba el
     * catálogo completo en una sola respuesta.
     */
    private const TOPE_DE_BUSQUEDA = 200;

    /**
     * Un id que viene de la URL. Null si no sirve, para que `filled()` lo trate
     * como ausente en vez de pasárselo a un findOrFail.
     */
    private function comoId(mixed $valor): ?int
    {
        // Ausente: no hay filtro.
        if ($valor === null || $valor === '') {
            return null;
        }

        // Presente pero ilegible (?marca[]=1, ?etiqueta=abc): se devuelve 0, que
        // no matchea nada. Antes se devolvía null y el filtro desaparecía sin
        // aviso: la pantalla mostraba el catálogo entero con el chip marcado
        // como activo. Un id numérico inexistente ya daba vacío; ahora uno mal
        // escrito hace lo mismo, en vez de abrir.
        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : 0;
    }

    /**
     * Texto que viene de la URL. Lo que no sea escalar se descarta, y se sacan
     * los comodines del LIKE: buscar "%" saltea el mínimo de dos caracteres y
     * vuelca el catálogo entero de un saque. Se quitan en vez de escaparlos
     * porque el escape con contrabarra no significa lo mismo en MySQL que en
     * SQLite, y los tests corren sobre SQLite.
     */
    private function comoTexto(mixed $valor): string
    {
        return is_scalar($valor) ? trim(str_replace(['%', '_'], '', (string) $valor)) : '';
    }

    public function index(Request $request)
    {
        // Los parámetros llegan de la barra de direcciones, así que pueden venir
        // como arreglo: ?marca[]=5 hacía explotar el findOrFail y ?buscar[]=x el
        // LIKE, con un error 500 en la cara del cliente. Se normalizan una sola
        // vez acá en vez de repetir la defensa en cada uno de los diez usos.
        $request->merge([
            'marca' => $this->comoId($request->input('marca')),
            'categoria' => $this->comoId($request->input('categoria')),
            'etiqueta' => $this->comoId($request->input('etiqueta')),
            'buscar' => $this->comoTexto($request->input('buscar')),
        ]);

        $vista = $request->input('vista');
        $marcas = Marca::activos()->orderBy('nombre')->get();
        $categorias = Categoria::activos()->orderBy('orden')->get();
        $filtros = $request->only(['marca', 'categoria', 'etiqueta', 'buscar', 'vista']);

        // --- SEARCH MODE: grouped by category ---
        // Dos letras como mínimo, igual que el buscador de la barra: con una
        // sola, "a" traía el catálogo entero.
        if (mb_strlen((string) $request->input('buscar')) >= 2) {
            $term = $request->buscar;
            $query = Producto::activos()
                ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                ->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', "%{$term}%")
                        ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', "%{$term}%"))
                        ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'like', "%{$term}%"));
                });

            // Los tres filtros, no solo la etiqueta: marca y categoría también
            // viajaban en `filtros` (o sea, la pantalla los mostraba puestos) y
            // esta rama nunca los aplicaba.
            $query->when($request->filled('etiqueta'), fn ($q) => $q->conEtiqueta((int) $request->etiqueta))
                ->when($request->filled('marca'), fn ($q) => $q->where('marca_id', (int) $request->marca))
                ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', (int) $request->categoria));

            // Paginado: sin esto, buscar una sola letra devolvía el catálogo
            // completo en una respuesta (24 MB con trescientos productos), y
            // esta ruta no tiene tope de intentos.
            $productos = $query->orderBy('nombre')->take(self::TOPE_DE_BUSQUEDA)->get();
            $porCategoria = $productos->groupBy(fn ($p) => $p->categoria->nombre ?? 'Sin categoría')
                ->sortKeys()
                ->map(fn ($items, $cat) => ['nombre' => $cat, 'productos' => $items->values()])
                ->values();

            return Inertia::render('Productos/Index', [
                'modo' => 'busqueda',
                'productosPorCategoria' => $porCategoria,
                'totalResultados' => $productos->count(),
                'productos' => null,
                'items' => null,
                'breadcrumb' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- CATEGORIAS MODE ---
        if ($vista === 'categorias') {
            if ($request->filled('categoria') && $request->filled('marca')) {
                $cat = Categoria::findOrFail($request->categoria);
                $marca = Marca::findOrFail($request->marca);
                $productos = Producto::activos()
                    ->where('categoria_id', $cat->id)
                    ->where('marca_id', $marca->id)
                    ->when($request->filled('etiqueta'), fn ($q) => $q->conEtiqueta((int) $request->etiqueta))
                    ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->paginate(24)->withQueryString();

                return Inertia::render('Productos/Index', [
                    'modo' => 'productos',
                    'productos' => $productos,
                    'breadcrumb' => [
                        ['label' => 'Categorías', 'url' => route('productos.index', ['vista' => 'categorias'])],
                        ['label' => $cat->nombre, 'url' => route('productos.index', ['vista' => 'categorias', 'categoria' => $cat->id])],
                        ['label' => $marca->nombre, 'url' => null],
                    ],
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'items' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            if ($request->filled('categoria')) {
                $cat = Categoria::findOrFail($request->categoria);
                $marcasEnCategoria = Marca::activos()
                    ->whereHas('productos', fn ($q) => $q->activos()->where('categoria_id', $cat->id))
                    ->withCount(['productos' => fn ($q) => $q->activos()->where('categoria_id', $cat->id)])
                    ->orderBy('nombre')
                    ->get();

                return Inertia::render('Productos/Index', [
                    'modo' => 'marcas_en_categoria',
                    'items' => $marcasEnCategoria,
                    'breadcrumb' => [
                        ['label' => 'Categorías', 'url' => route('productos.index', ['vista' => 'categorias'])],
                        ['label' => $cat->nombre, 'url' => null],
                    ],
                    'categoriaActual' => $cat,
                    'productos' => null,
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            $categoriasConCount = Categoria::activos()
                ->whereHas('productos', fn ($q) => $q->where('activo', true))
                ->withCount(['productos' => fn ($q) => $q->activos()])
                ->orderBy('orden')
                ->get();

            return Inertia::render('Productos/Index', [
                'modo' => 'categorias',
                'items' => $categoriasConCount,
                'breadcrumb' => [['label' => 'Categorías', 'url' => null]],
                'productos' => null,
                'productosPorCategoria' => null,
                'totalResultados' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- MARCAS MODE ---
        if ($vista === 'marcas') {
            if ($request->filled('marca') && $request->filled('categoria')) {
                $marca = Marca::findOrFail($request->marca);
                $cat = Categoria::findOrFail($request->categoria);
                $productos = Producto::activos()
                    ->where('marca_id', $marca->id)
                    ->where('categoria_id', $cat->id)
                    ->when($request->filled('etiqueta'), fn ($q) => $q->conEtiqueta((int) $request->etiqueta))
                    ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->paginate(24)->withQueryString();

                return Inertia::render('Productos/Index', [
                    'modo' => 'productos',
                    'productos' => $productos,
                    'breadcrumb' => [
                        ['label' => 'Marcas', 'url' => route('productos.index', ['vista' => 'marcas'])],
                        ['label' => $marca->nombre, 'url' => route('productos.index', ['vista' => 'marcas', 'marca' => $marca->id])],
                        ['label' => $cat->nombre, 'url' => null],
                    ],
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'items' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            if ($request->filled('marca')) {
                $marca = Marca::findOrFail($request->marca);
                $categoriasEnMarca = Categoria::activos()
                    ->whereHas('productos', fn ($q) => $q->activos()->where('marca_id', $marca->id))
                    ->withCount(['productos' => fn ($q) => $q->activos()->where('marca_id', $marca->id)])
                    ->orderBy('orden')
                    ->get();

                // Además de las categorías, se listan los productos de la marca
                // acá mismo: si no, para ver un producto había que entrar sí o
                // sí a una categoría.
                $productosDeMarca = Producto::activos()
                    ->where('marca_id', $marca->id)
                    ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->when($request->filled('etiqueta'), fn ($q) => $q->conEtiqueta((int) $request->etiqueta))
                    ->paginate(24)
                    ->withQueryString();

                return Inertia::render('Productos/Index', [
                    'modo' => 'categorias_en_marca',
                    'items' => $categoriasEnMarca,
                    'breadcrumb' => [
                        ['label' => 'Marcas', 'url' => route('productos.index', ['vista' => 'marcas'])],
                        ['label' => $marca->nombre, 'url' => null],
                    ],
                    'marcaActual' => $marca,
                    'productos' => $productosDeMarca,
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            $marcasConCount = Marca::activos()
                ->whereHas('productos', fn ($q) => $q->where('activo', true))
                ->withCount(['productos' => fn ($q) => $q->activos()])
                ->orderBy('nombre')
                ->get();

            return Inertia::render('Productos/Index', [
                'modo' => 'marcas',
                'items' => $marcasConCount,
                'breadcrumb' => [['label' => 'Marcas', 'url' => null]],
                'productos' => null,
                'productosPorCategoria' => null,
                'totalResultados' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- DEFAULT: flat product listing ---
        $query = Producto::activos()
            ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()]);

        if ($request->filled('marca')) {
            $query->where('marca_id', $request->marca);
        }
        if ($request->filled('categoria')) {
            $query->where('categoria_id', $request->categoria);
        }
        if ($request->filled('etiqueta')) {
            $query->conEtiqueta((int) $request->etiqueta);
        }

        $productos = $query->orderBy('nombre')->paginate(24)->withQueryString();

        return Inertia::render('Productos/Index', [
            'modo' => 'productos',
            'productos' => $productos,
            'breadcrumb' => null,
            'productosPorCategoria' => null,
            'totalResultados' => null,
            'items' => null,
            'marcas' => $marcas,
            'categorias' => $categorias,
            'filtros' => $filtros,
        ]);
    }

    public function show(Producto $producto)
    {
        // Un producto apagado en el panel no tiene página pública: seguía
        // sirviéndose por link directo (y quedando indexable) aunque ya no
        // apareciera en ningún listado ni en el buscador.
        abort_unless($producto->activo, 404);

        $producto->load(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()]);

        $relacionados = Producto::activos()
            ->where('categoria_id', $producto->categoria_id)
            ->where('id', '!=', $producto->id)
            ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
            ->take(6)->get();

        return Inertia::render('Productos/Show', [
            'producto' => $producto,
            'relacionados' => $relacionados,
        ]);
    }

    public function buscar(Request $request)
    {
        // Mismo motivo que en index(): ?q[]=x le pasaba un arreglo a strlen() y
        // devolvía un 500 (con APP_DEBUG prendido, además, la traza entera).
        $q = $this->comoTexto($request->input('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $productos = Producto::activos()
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', "%{$q}%"));
            })
            ->with('marca:id,nombre')
            ->select('id', 'nombre', 'slug', 'marca_id', 'imagen')
            ->take(8)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'marca' => $p->marca->nombre ?? '',
                'slug' => $p->slug,
                'imagen' => $p->imagen_url,
            ]);

        return response()->json($productos);
    }
}
