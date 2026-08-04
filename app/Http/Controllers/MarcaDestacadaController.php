<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Producto;
use Inertia\Inertia;

/**
 * Sección "marca destacada" del menú: muestra los productos de la marca
 * propia del negocio, elegida en el panel (Configuración). Si no hay marca
 * configurada la sección no existe (el layout tampoco muestra el link).
 */
class MarcaDestacadaController extends Controller
{
    public function __invoke()
    {
        $marca = Configuracion::actual()->marcaDestacada;

        abort_if($marca === null, 404);

        $productos = Producto::activos()
            ->where('marca_id', $marca->id)
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->paginate(24);

        return Inertia::render('MarcaDestacada', [
            'marca' => $marca->only(['id', 'nombre']),
            'productos' => $productos,
        ]);
    }
}
