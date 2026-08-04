<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CargarPedido extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Cargar pedido';

    protected static ?string $title = 'Cargar pedido para un cliente';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.cargar-pedido';

    public ?string $cliente_id = null;

    public string $entrega = 'envio';

    public ?string $notas = null;

    public string $busqueda = '';

    public array $resultados = [];

    /** @var array<int, array{presentacion_id:int,nombre:string,marca:?string,unidad:string,precio:float,cantidad:int}> */
    public array $items = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isOperador();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('cliente_id')
                ->label('Cliente')
                ->options(fn () => User::where('role', 'cliente')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (User $u) => [
                        $u->id => $u->name.($u->negocio ? " ({$u->negocio})" : '').' — '.$u->email,
                    ]))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('entrega')
                ->label('Entrega')
                ->options([
                    'envio' => 'Envío a domicilio',
                    'retiro' => 'Retiro en local',
                ])
                ->default('envio')
                ->required(),
            Forms\Components\Textarea::make('notas')
                ->label('Notas (opcional)')
                ->rows(2),
        ]);
    }

    public function updatedBusqueda(): void
    {
        $texto = trim($this->busqueda);

        if (mb_strlen($texto) < 2) {
            $this->resultados = [];

            return;
        }

        $marcaIds = Marca::where('nombre', 'like', "%{$texto}%")->pluck('id');
        $productoIds = Producto::activos()
            ->where(function ($q) use ($texto, $marcaIds) {
                $q->where('nombre', 'like', "%{$texto}%")
                    ->orWhereIn('marca_id', $marcaIds);
            })
            ->pluck('id');

        $productos = [];
        foreach (Producto::whereIn('id', $productoIds->all())->with('marca')->get() as $producto) {
            $productos[$producto->id] = $producto;
        }

        $presentaciones = Presentacion::activos()
            ->whereIn('producto_id', array_keys($productos))
            ->orderBy('unidad')
            ->limit(15)
            ->get();

        $resultados = [];
        foreach ($presentaciones as $p) {
            $producto = $productos[$p->producto_id] ?? null;
            $resultados[] = [
                'id' => $p->id,
                'nombre' => $producto?->nombre,
                'marca' => $producto?->marca?->nombre,
                'unidad' => $p->unidad,
                'precio' => $this->precioDe($p),
                'stock' => $p->stock,
            ];
        }
        $this->resultados = $resultados;
    }

    public function agregarProducto(int $presentacionId): void
    {
        if (isset($this->items[$presentacionId])) {
            $this->items[$presentacionId]['cantidad']++;
        } else {
            $p = Presentacion::find($presentacionId);

            if (! $p) {
                return;
            }

            $producto = Producto::with('marca')->find($p->producto_id);

            if (! $producto) {
                return;
            }

            $this->items[$presentacionId] = [
                'presentacion_id' => $p->id,
                'nombre' => $producto->nombre,
                'marca' => $producto->marca?->nombre,
                'unidad' => $p->unidad,
                'precio' => $this->precioDe($p),
                'cantidad' => 1,
            ];
        }

        $this->recalcularPrecios();

        $this->busqueda = '';
        $this->resultados = [];
    }

    public function cambiarCantidad(int $presentacionId, int $delta): void
    {
        if (! isset($this->items[$presentacionId])) {
            return;
        }

        $nueva = $this->items[$presentacionId]['cantidad'] + $delta;

        if ($nueva <= 0) {
            unset($this->items[$presentacionId]);

            return;
        }

        $this->items[$presentacionId]['cantidad'] = $nueva;
        $this->recalcularPrecios();
    }

    public function quitarProducto(int $presentacionId): void
    {
        unset($this->items[$presentacionId]);
    }

    /**
     * Cambiar de cliente cambia los precios (un negocio paga por mayor), así
     * que lo ya cargado se revisa en vez de quedar con los precios del anterior.
     */
    public function updatedClienteId(): void
    {
        $this->recalcularPrecios();
    }

    private function clienteElegido(): ?User
    {
        return $this->cliente_id ? User::find($this->cliente_id) : null;
    }

    private function precioDe(Presentacion $presentacion, int $cantidad = 1): float
    {
        return $presentacion->precioPara($this->clienteElegido(), $cantidad);
    }

    private function recalcularPrecios(): void
    {
        $cliente = $this->clienteElegido();

        foreach ($this->items as $id => $item) {
            $presentacion = Presentacion::find($item['presentacion_id']);

            if ($presentacion) {
                $this->items[$id]['precio'] = $presentacion->precioPara($cliente, (int) $item['cantidad']);
            }
        }
    }

    public function getTotalProperty(): float
    {
        return collect($this->items)->sum(fn (array $i) => $i['precio'] * $i['cantidad']);
    }

    public function crearPedido(): void
    {
        $this->validate([
            'cliente_id' => ['required', 'exists:users,id'],
        ]);

        if (empty($this->items)) {
            Notification::make()
                ->title('Agregá al menos un producto antes de crear el pedido')
                ->warning()
                ->send();

            return;
        }

        $user = User::find($this->cliente_id);

        try {
            $pedido = DB::transaction(function () use ($user) {
                $pedido = Pedido::create([
                    'user_id' => $user->id,
                    'estado' => 'pending',
                    'total' => 0,
                    'datos_cliente' => [
                        'nombre' => $user->name,
                        'negocio' => $user->negocio,
                        'tipo_cliente' => $user->tipo_cliente,
                        'email' => $user->email,
                        'celular' => $user->celular,
                        'direccion' => $user->direccion,
                        'ciudad' => $user->ciudad,
                        'provincia' => $user->provincia,
                        'entrega' => $this->entrega,
                        'notas' => $this->notas,
                    ],
                ]);

                foreach ($this->items as $item) {
                    // El precio se resuelve acá contra el cliente elegido y la
                    // cantidad final, no se confía en el que quedó en pantalla:
                    // el cliente pudo cambiarse después de agregar los productos.
                    $presentacion = Presentacion::find($item['presentacion_id']);
                    $precio = $presentacion
                        ? $presentacion->precioPara($user, (int) $item['cantidad'])
                        : (float) $item['precio'];

                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'presentacion_id' => $item['presentacion_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $precio,
                        'subtotal' => round($precio * $item['cantidad'], 2),
                    ]);
                }

                $pedido->recalcularTotal();

                return $pedido;
            });
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo crear el pedido')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->send();

            return;
        }

        $this->reset(['cliente_id', 'entrega', 'notas', 'busqueda', 'resultados', 'items']);
        $this->entrega = 'envio';

        Notification::make()
            ->title("Pedido #{$pedido->id} creado para {$user->name}")
            ->success()
            ->send();

        $this->redirect(PedidoResource::getUrl('view', ['record' => $pedido]));
    }
}
