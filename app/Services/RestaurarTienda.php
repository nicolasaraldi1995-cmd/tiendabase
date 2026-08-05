<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\SeccionMenu;
use Illuminate\Support\Facades\DB;

/**
 * "Restaurar valores de fábrica": deja la tienda como recién instalada en lo
 * que hace a su configuración, su menú y su catálogo.
 *
 * NO toca pedidos, pagos, gastos ni clientes: son el historial del negocio y
 * borrarlos por error no tendría vuelta.
 */
class RestaurarTienda
{
    /**
     * @return array<string, int> lo que se borró, para poder informarlo
     */
    public function ejecutar(): array
    {
        return DB::transaction(function () {
            $borrado = [
                'productos' => Producto::count(),
                'marcas' => Marca::count(),
                'categorias' => Categoria::count(),
                'combos' => Combo::count(),
                'banners' => Banner::count(),
            ];

            $this->vaciarCatalogo();
            $this->restaurarMenu();
            $this->restaurarConfiguracion();

            return $borrado;
        });
    }

    /**
     * Con pedidos en la base, el catálogo se archiva en vez de borrarse: los
     * pedidos apuntan a las presentaciones (con restrictOnDelete), así que
     * borrarlas de verdad rompería el historial de ventas. Sin pedidos, se
     * borra del todo y la tienda queda realmente limpia.
     */
    private function vaciarCatalogo(): void
    {
        if (PedidoItem::exists()) {
            Combo::query()->delete();
            Banner::query()->delete();
            Presentacion::query()->delete();
            Producto::query()->delete();
            Marca::query()->delete();
            Categoria::query()->delete();

            return;
        }

        // El orden importa: primero lo que depende de otra cosa.
        Combo::withTrashed()->forceDelete();
        Banner::withTrashed()->forceDelete();
        Presentacion::withTrashed()->forceDelete();
        Producto::withTrashed()->forceDelete();
        Marca::withTrashed()->forceDelete();
        Categoria::withTrashed()->forceDelete();
    }

    private function restaurarMenu(): void
    {
        SeccionMenu::query()->delete();

        foreach (SeccionMenu::POR_DEFECTO as $i => $item) {
            SeccionMenu::create($item + ['orden' => ($i + 1) * 10, 'activo' => true]);
        }
    }

    private function restaurarConfiguracion(): void
    {
        Configuracion::actual()->update([
            'nombre_negocio' => 'Mi Tienda',
            'eslogan' => null,
            'descripcion' => null,
            'direccion' => null,
            'ciudad' => null,
            'telefono' => null,
            'whatsapp' => null,
            'instagram' => null,
            'logo' => null,
            'medios_pago' => null,
            'color_acento' => null,
            'plantilla' => 'catalogo',
            'tipografia' => 'inter',
            ...Configuracion::DEFAULTS_DE_MEDIDA,
            'email_avisos' => null,
            'marca_destacada_id' => null,
            'envio_gratis_desde' => 0,
            'pedido_minimo_mayorista' => 0,
            'controlar_stock' => true,
            'mostrar_filtros_alimentos' => true,
            'mostrar_lista_precios' => true,
            'mostrar_combos' => true,
            'mostrar_ofertas' => true,
        ]);
    }
}
