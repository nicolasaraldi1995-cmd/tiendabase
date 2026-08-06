<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Inertia\Inertia;

class CategoriaController extends Controller
{
    public function show(Categoria $categoria)
    {
        // Mismo criterio que con un producto apagado: si el negocio la sacó, no
        // tiene página pública. Antes seguía sirviéndose (e indexándose) por
        // link directo aunque no apareciera en ningún menú.
        abort_unless($categoria->activo, 404);

        $productos = $categoria->productos()
            ->activos()
            ->with(['marca', 'categoria', 'etiquetas' => fn ($e) => $e->activas(), 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->paginate(24);

        return Inertia::render('Categorias/Show', [
            'categoria' => $categoria,
            'productos' => $productos,
        ]);
    }
}
