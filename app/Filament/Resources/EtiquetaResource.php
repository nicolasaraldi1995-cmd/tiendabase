<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EtiquetaResource\Pages;
use App\Models\Etiqueta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EtiquetaResource extends Resource
{
    protected static ?string $model = Etiqueta::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Etiquetas';

    protected static ?string $modelLabel = 'etiqueta';

    protected static ?string $pluralModelLabel = 'etiquetas';

    protected static ?int $navigationSort = 16;

    /**
     * Solo el admin: una etiqueta con aviso cambia lo que ve el cliente en el
     * carrito, y decidir qué se le promete no es tarea del operador.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Como lo va a ver tu cliente. Ej: "Sin TACC", "Inoxidable", "Bajo pedido", "Importado", "Planta de interior".'),
            Forms\Components\ColorPicker::make('color')
                ->label('Color')
                ->nullable()
                ->helperText('El color del cartelito sobre la foto del producto. Dejalo vacío para usar el color principal de tu tienda.'),
            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->numeric()
                ->default(0)
                ->helperText('De menor a mayor: define en qué orden se listan los filtros en el menú.'),
            Forms\Components\Toggle::make('en_filtros')
                ->label('Mostrar como filtro en el menú')
                ->default(true)
                ->helperText('Le agrega un acceso al menú de la tienda para ver solo los productos con esta etiqueta. Si es una etiqueta que usás en pocos productos, quizás no valga la pena.'),
            Forms\Components\TextInput::make('aviso')
                ->label('Aviso en el carrito')
                ->maxLength(255)
                ->helperText('Si el pedido lleva un producto con esta etiqueta, el carrito muestra este texto y le ofrece sacarlos. Ej: "Consultá disponibilidad para tu zona antes de confirmar" o "Bajo pedido: puede demorar 5 días". Dejalo vacío si esta etiqueta no necesita avisar nada.'),
            Forms\Components\Toggle::make('activo')
                ->label('Activa')
                ->default(true)
                ->helperText('Si la apagás, deja de verse en la tienda pero no se borra ni se despega de tus productos.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->default('#9ca3af'),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Productos'),
                Tables\Columns\IconColumn::make('en_filtros')
                    ->label('En el menú')
                    ->boolean(),
                Tables\Columns\TextColumn::make('aviso')
                    ->label('Aviso en el carrito')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\ToggleColumn::make('activo')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEtiquetas::route('/'),
            'create' => Pages\CreateEtiqueta::route('/create'),
            'edit' => Pages\EditEtiqueta::route('/{record}/edit'),
        ];
    }
}
