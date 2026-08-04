<?php

namespace App\Filament\Pages;

use App\Models\Configuracion as ConfiguracionModel;
use App\Models\Marca;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property-read Form $form Propiedad mágica de InteractsWithForms.
 */
class Configuracion extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del sitio';

    protected static ?int $navigationSort = 43;

    protected static string $view = 'filament.pages.configuracion';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(ConfiguracionModel::actual()->only([
            'nombre_negocio', 'eslogan', 'descripcion', 'direccion', 'ciudad',
            'telefono', 'whatsapp', 'instagram', 'logo', 'medios_pago',
            'color_acento', 'marca_destacada_id', 'envio_gratis_desde', 'controlar_stock',
            'mostrar_filtros_alimentos', 'mostrar_lista_precios', 'mostrar_combos', 'mostrar_ofertas',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Identidad del negocio')
                ->description('El nombre, logo y textos que ven tus clientes en la tienda, los PDFs y los emails.')
                ->schema([
                    Forms\Components\TextInput::make('nombre_negocio')
                        ->label('Nombre del negocio')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('eslogan')
                        ->label('Eslogan')
                        ->maxLength(255)
                        ->helperText('Frase corta debajo del nombre, ej: "Distribuidora mayorista". Opcional.'),
                    Forms\Components\Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Se muestra en el pie de página y en los buscadores. Opcional.'),
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->maxSize(4096)
                        ->directory('branding')
                        ->visibility('public')
                        ->imagePreviewHeight('80')
                        ->helperText('Si no cargás logo, se muestra el nombre del negocio en texto.'),
                    Forms\Components\ColorPicker::make('color_acento')
                        ->label('Color principal')
                        ->nullable()
                        ->helperText('Botones, links y detalles de la tienda (también en PDFs y emails). Elegí un tono medio u oscuro: el texto encima va en blanco. Dejalo vacío para usar el color original.'),
                ]),
            Forms\Components\Section::make('Contacto y redes')
                ->description('Todo es opcional: lo que dejés vacío no aparece en la página.')
                ->schema([
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('ciudad')->label('Ciudad / provincia')->maxLength(255),
                    Forms\Components\TextInput::make('telefono')->label('Teléfono')->maxLength(255),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->maxLength(30)
                        ->regex('/^\d+$/')
                        ->helperText('Solo números, con código de país y sin el 15. Ej: 5492477504048. Activa el botón flotante de la tienda.'),
                    Forms\Components\TextInput::make('instagram')
                        ->label('Instagram')
                        ->url()
                        ->maxLength(255)
                        ->helperText('El link completo, ej: https://www.instagram.com/tunegocio'),
                    Forms\Components\TextInput::make('medios_pago')
                        ->label('Medios de pago')
                        ->maxLength(255)
                        ->helperText('Separados por coma, ej: Efectivo, Transferencia, MercadoPago. Se muestran en el pie de página.'),
                ]),
            Forms\Components\Section::make('Marca destacada')
                ->description('Si tenés productos de marca propia, elegila acá: aparece como sección propia en el menú de la tienda.')
                ->schema([
                    Forms\Components\Select::make('marca_destacada_id')
                        ->label('Marca')
                        ->options(fn () => Marca::orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin marca destacada'),
                ]),
            Forms\Components\Section::make('Secciones de la tienda')
                ->description('Apagá lo que tu rubro no usa: desaparece del menú y de la página, y podés volver a prenderlo cuando quieras.')
                ->schema([
                    Forms\Components\Toggle::make('mostrar_filtros_alimentos')
                        ->label('Filtros de alimentos')
                        ->helperText('Los filtros Sin TACC / Fríos / Congelados del menú y el aviso de productos fríos en el checkout. Para negocios que no venden comida, apagalo.'),
                    Forms\Components\Toggle::make('mostrar_combos')
                        ->label('Combos')
                        ->helperText('La sección Combos del menú y de la portada.'),
                    Forms\Components\Toggle::make('mostrar_ofertas')
                        ->label('Ofertas')
                        ->helperText('La sección Ofertas del menú.'),
                    Forms\Components\Toggle::make('mostrar_lista_precios')
                        ->label('Lista de precios')
                        ->helperText('La herramienta interna de lista de precios (HTML/PDF) que ve tu personal. Pensada para venta mayorista.'),
                ]),
            Forms\Components\Section::make('Envío')
                ->description('Definí a partir de qué monto de compra el envío sale gratis.')
                ->schema([
                    Forms\Components\TextInput::make('envio_gratis_desde')
                        ->label('Envío gratis a partir de')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('Se muestra en el carrito y en el checkout. Dejalo en 0 si no ofrecés envío gratis: la franja no se muestra.'),
                ]),
            Forms\Components\Section::make('Stock')
                ->description('Definí si el stock cargado limita lo que se puede comprar.')
                ->schema([
                    Forms\Components\Toggle::make('controlar_stock')
                        ->label('Controlar stock')
                        ->helperText('Si lo apagás, se puede comprar cualquier producto en cualquier cantidad sin importar el stock cargado. El número de stock sigue existiendo y se sigue actualizando con cada pedido (podés reactivar el control cuando quieras), solo deja de frenar la compra.'),
                ]),
        ]);
    }

    public function guardar(): void
    {
        ConfiguracionModel::actual()->update($this->form->getState());

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
