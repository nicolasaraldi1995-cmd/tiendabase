<?php

namespace App\Http\Controllers;

use App\Models\Pagina;
use Inertia\Inertia;

class PaginaController extends Controller
{
    public function show(string $slug)
    {
        $pagina = Pagina::activos()->where('slug', $slug)->firstOrFail();

        return Inertia::render('Pagina', [
            'pagina' => $pagina->only(['titulo', 'contenido']),
        ]);
    }
}
