<?php

namespace App\Http\Controllers;

use App\Models\SeccionMenu;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El editor del menú que el dueño usa sobre su propia tienda (ver
 * MenuEditor.vue). El panel sigue teniendo la pantalla completa; esto es el
 * atajo: arrastrar, renombrar y agregar sin salir de donde lo está viendo.
 */
class MenuTiendaController extends Controller
{
    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());

        SeccionMenu::create($datos + [
            'orden' => (SeccionMenu::max('orden') ?? 0) + 10,
            'activo' => true,
        ]);

        return back();
    }

    public function update(Request $request, SeccionMenu $seccion)
    {
        // Se editan solo los campos que el editor de la tienda expone: el
        // destino se cambia desde el panel, donde se ve la lista completa.
        $seccion->update($request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'activo' => ['required', 'boolean'],
        ]));

        return back();
    }

    public function destroy(SeccionMenu $seccion)
    {
        $seccion->delete();

        return back();
    }

    public function reordenar(Request $request)
    {
        $datos = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:secciones_menu,id'],
        ]);

        foreach ($datos['ids'] as $posicion => $id) {
            SeccionMenu::whereKey($id)->update(['orden' => ($posicion + 1) * 10]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'destino_tipo' => ['required', Rule::in(array_keys(SeccionMenu::DESTINOS))],
            // Obligatorio solo para los destinos que apuntan a algo puntual
            // (una categoría, una marca, una página, un link).
            'destino_valor' => [
                Rule::requiredIf(fn () => in_array(request('destino_tipo'), SeccionMenu::DESTINOS_CON_VALOR, true)),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
