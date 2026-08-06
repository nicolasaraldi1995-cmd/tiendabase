<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Etiqueta;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 10;

    /**
     * Formatos de imagen que se aceptan al subir. Sin esta lista, ->image()
     * valida "mimetypes:image/*" y ahí entra el SVG, que es texto y puede
     * traer un <script> adentro.
     */
    public const IMAGENES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    /**
     * Filament deja vacío el chequeo de acceso de las pantallas de recurso, así
     * que montándolas por dentro se salteaba la dirección (ver
     * App\Filament\Concerns\ExigeAccesoAlRecurso).
     */
    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return (bool) ($usuario?->isAdmin() || $usuario?->isOperador());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Producto')->tabs([
                Forms\Components\Tabs\Tab::make('Datos')->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('marca_id')
                        ->relationship('marca', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->required(),
                        ]),
                    Forms\Components\Select::make('categoria_id')
                        ->relationship('categoria', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->required(),
                        ]),
                    Forms\Components\Textarea::make('descripcion')
                        ->rows(3),
                    Forms\Components\Select::make('etiquetas')
                        ->label('Etiquetas')
                        ->relationship('etiquetas', 'nombre')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->unique('etiquetas', 'nombre'),
                            Forms\Components\ColorPicker::make('color')->label('Color')->nullable(),
                        ])
                        ->helperText('Se cargan en Catálogo → Etiquetas. Aparecen sobre la foto del producto y, si la etiqueta lo tiene puesto, también como filtro en el menú de la tienda.'),
                    Forms\Components\Toggle::make('nuevo'),
                    Forms\Components\Toggle::make('activo')->default(true),
                ]),
                Forms\Components\Tabs\Tab::make('Presentaciones')->schema([
                    Forms\Components\Repeater::make('presentaciones')
                        ->relationship()
                        ->schema([
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('unidad')
                                    ->required()
                                    ->placeholder('ej: 500gr'),
                                Forms\Components\TextInput::make('sku')
                                    ->placeholder('Código opcional'),
                                Forms\Components\TextInput::make('precio')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0)
                                    ->prefix('$')
                                    ->visible(fn () => auth()->user()?->isAdmin())
                                    ->dehydratedWhenHidden(),
                                Forms\Components\TextInput::make('stock')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),
                            ]),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('precio_costo')
                                    ->label('Precio de costo')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                                Forms\Components\TextInput::make('descuento_porcentaje')
                                    ->label('Descuento proveedor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->afterStateHydrated(fn (Forms\Components\TextInput $component, Forms\Get $get) => self::heredarDeMarcaSiVacio($component, $get, 'descuento_porcentaje'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                                Forms\Components\TextInput::make('margen_porcentaje')
                                    ->label('Margen de ganancia')
                                    ->numeric()
                                    ->minValue(-99)
                                    ->maxValue(500)
                                    ->suffix('%')
                                    ->afterStateHydrated(fn (Forms\Components\TextInput $component, Forms\Get $get) => self::heredarDeMarcaSiVacio($component, $get, 'margen_porcentaje'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set))
                                    ->helperText('Completá costo y margen para calcular el precio de arriba solo.'),
                                Forms\Components\Toggle::make('iva')
                                    ->label('IVA (21%)')
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularPrecio($get, $set)),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('precio_mayorista')
                                    ->label('Precio por mayor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->lte('precio')
                                    ->validationMessages(['lte' => 'El precio por mayor no puede ser más caro que el precio normal.'])
                                    ->prefix('$')
                                    ->helperText('Lo pagan siempre los clientes de tipo negocio. Vacío = todos pagan el precio normal.'),
                                Forms\Components\TextInput::make('cantidad_mayorista')
                                    ->label('...o desde esta cantidad')
                                    ->numeric()
                                    ->minValue(2)
                                    ->suffix('u.')
                                    ->helperText('Cualquier cliente que lleve esta cantidad o más paga el precio por mayor. Vacío = solo los negocios.'),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('oferta_porcentaje')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(90)
                                    ->suffix('%')
                                    ->label('Oferta %')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::recalcularOferta($get, $set)),
                                Forms\Components\TextInput::make('oferta_precio')
                                    ->numeric()
                                    ->minValue(0)
                                    ->lt('precio')
                                    ->validationMessages(['lt' => 'El precio de oferta tiene que ser menor al precio normal.'])
                                    ->prefix('$')
                                    ->label('Precio oferta'),
                                Forms\Components\DatePicker::make('oferta_inicio')
                                    ->label('Inicio oferta'),
                                Forms\Components\DatePicker::make('oferta_fin')
                                    ->label('Fin oferta'),
                            ])->visible(fn () => auth()->user()?->isAdmin()),
                            Forms\Components\FileUpload::make('imagen')
                                ->image()
                                ->acceptedFileTypes(self::IMAGENES)
                                ->maxSize(5120)
                                ->directory('presentaciones')
                                ->visibility('public')
                                ->imagePreviewHeight('100')
                                ->label('Imagen'),
                            Forms\Components\Toggle::make('activo')->default(true),
                        ])
                        // Mismo motivo que en los pedidos: los campos de plata
                        // están ocultos para el operador pero se deshidratan, así
                        // que el servidor los repone desde la base.
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::soloElAdminPoneElPrecio($data, null))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Presentacion $record) => self::soloElAdminPoneElPrecio($data, $record))
                        ->defaultItems(1)
                        ->addActionLabel('Agregar presentación')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['unidad'] ?? null),
                ]),
                Forms\Components\Tabs\Tab::make('Imagen')->schema([
                    Forms\Components\FileUpload::make('imagen')
                        ->image()
                        ->acceptedFileTypes(self::IMAGENES)
                        ->maxSize(5120)
                        ->directory('productos')
                        ->visibility('public')
                        ->imagePreviewHeight('200'),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * Los precios que escribió el operador se descartan y se reponen desde la
     * base. Sin esto, ocultarle los campos no servía de nada.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function soloElAdminPoneElPrecio(array $data, ?Presentacion $guardada): array
    {
        if (auth()->user()?->isAdmin() ?? false) {
            return $data;
        }

        foreach ([
            'precio', 'precio_costo', 'margen_porcentaje', 'descuento_porcentaje',
            'oferta_precio', 'oferta_porcentaje',
            'precio_mayorista', 'cantidad_mayorista',
        ] as $campo) {
            $data[$campo] = $guardada->{$campo} ?? ($campo === 'precio' ? 0 : null);
        }

        return $data;
    }

    private static function heredarDeMarcaSiVacio(Forms\Components\TextInput $component, Forms\Get $get, string $campo): void
    {
        // Si no, el gancho le vuelve a poner el margen de la marca justo
        // después de que el servidor se lo recortó.
        if (! (auth()->user()?->isAdmin() ?? false)) {
            return;
        }

        if (filled($component->getState())) {
            return;
        }

        $component->state(Marca::find($get('../../marca_id'))?->{$campo});
    }

    private static function recalcularPrecio(Forms\Get $get, Forms\Set $set): void
    {
        $costo = $get('precio_costo');
        $margen = $get('margen_porcentaje');

        if ($costo === null || $costo === '' || $margen === null || $margen === '') {
            return;
        }

        $descuento = (float) ($get('descuento_porcentaje') ?? 0);

        $precio = (float) $costo * (1 - $descuento / 100) * (1 + (float) $margen / 100);

        if ($get('iva')) {
            $precio *= 1.21;
        }

        $set('precio', round($precio, 2));

        // Si ya había una oferta % cargada, su precio de oferta quedaba con
        // el valor viejo (calculado sobre el precio anterior) hasta que se
        // volviera a tocar "Oferta %" a mano.
        self::recalcularOferta($get, $set);
    }

    private static function recalcularOferta(Forms\Get $get, Forms\Set $set): void
    {
        $porcentaje = $get('oferta_porcentaje');
        $precio = (float) ($get('precio') ?? 0);

        if ($porcentaje === null || $porcentaje === '' || $precio <= 0) {
            return;
        }

        $set('oferta_precio', round($precio * (1 - (float) $porcentaje / 100), 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')
                    ->checkFileExistence(false)
                    ->circular(),
                // Marca visual para encontrar de un vistazo los productos que
                // quedaron sin foto (hay ~189 tras perderse el disco viejo).
                Tables\Columns\IconColumn::make('sin_imagen')
                    ->label('')
                    ->getStateUsing(fn (Producto $record) => blank($record->imagen))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('')
                    ->tooltip(fn (Producto $record) => blank($record->imagen) ? 'Sin foto' : null),
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('marca.nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('categoria.nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('presentaciones_count')
                    ->counts('presentaciones')
                    ->label('Pres.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('etiquetas.nombre')
                    ->label('Etiquetas')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\ToggleColumn::make('nuevo'),
                Tables\Columns\ToggleColumn::make('activo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marca_id')
                    ->relationship('marca', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Marca'),
                Tables\Filters\SelectFilter::make('categoria_id')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->label('Categoría'),
                Tables\Filters\Filter::make('sin_imagen')
                    ->label('Sin foto')
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('imagen')->orWhere('imagen', '')))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('etiquetas')
                    ->label('Etiqueta')
                    ->relationship('etiquetas', 'nombre')
                    ->multiple()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('nuevo'),
                Tables\Filters\TernaryFilter::make('activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Genéricas: antes había seis acciones fijas, dos por cada
                    // columna de alimentos. Con etiquetas libres alcanzan dos.
                    Tables\Actions\BulkAction::make('agregar_etiqueta')
                        ->label('Agregar etiqueta')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('etiqueta_id')
                                ->label('Etiqueta')
                                ->options(fn () => Etiqueta::orderBy('nombre')->pluck('nombre', 'id'))
                                ->required(),
                        ])
                        ->action(fn ($records, array $data) => $records->each(fn ($r) => $r->etiquetas()->syncWithoutDetaching([$data['etiqueta_id']])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_etiqueta')
                        ->label('Quitar etiqueta')
                        ->icon('heroicon-o-x-mark')
                        ->form([
                            Forms\Components\Select::make('etiqueta_id')
                                ->label('Etiqueta')
                                ->options(fn () => Etiqueta::orderBy('nombre')->pluck('nombre', 'id'))
                                ->required(),
                        ])
                        ->action(fn ($records, array $data) => $records->each(fn ($r) => $r->etiquetas()->detach($data['etiqueta_id'])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('marcar_nuevo')
                        ->label('Marcar Nuevo')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['nuevo' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('quitar_nuevo')
                        ->label('Quitar Nuevo')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['nuevo' => false])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
