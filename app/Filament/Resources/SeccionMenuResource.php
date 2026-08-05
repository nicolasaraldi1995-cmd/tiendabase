<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeccionMenuResource\Pages;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pagina;
use App\Models\SeccionMenu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeccionMenuResource extends Resource
{
    protected static ?string $model = SeccionMenu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Menú de la tienda';

    protected static ?string $modelLabel = 'sección del menú';

    protected static ?string $pluralModelLabel = 'secciones del menú';

    protected static ?int $navigationSort = 15;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titulo')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->helperText('Como se va a ver en el menú de tu tienda. Ej: "Juguetes", "Electrodomésticos", "Ofertas de invierno".'),
            Forms\Components\TextInput::make('emoji')
                ->label('Emoji')
                ->maxLength(16)
                ->helperText('El dibujito que acompaña al nombre. Podés copiar y pegar cualquiera: 🧸 🔌 🍫 🧴 🛠️ 🎁'),
            Forms\Components\Select::make('destino_tipo')
                ->label('¿A dónde lleva?')
                ->options(SeccionMenu::DESTINOS)
                ->required()
                ->live()
                ->default('categoria'),
            Forms\Components\Select::make('destino_valor')
                ->label('¿Cuál?')
                ->required()
                ->options(fn (Forms\Get $get) => match ($get('destino_tipo')) {
                    'categoria' => Categoria::orderBy('nombre')->pluck('nombre', 'id'),
                    'marca' => Marca::orderBy('nombre')->pluck('nombre', 'id'),
                    'pagina' => Pagina::orderBy('titulo')->pluck('titulo', 'slug'),
                    default => [],
                })
                ->searchable()
                ->visible(fn (Forms\Get $get) => in_array($get('destino_tipo'), ['categoria', 'marca', 'pagina'], true)),
            Forms\Components\TextInput::make('destino_valor')
                ->label('Dirección web')
                ->url()
                ->required()
                ->placeholder('https://...')
                ->visible(fn (Forms\Get $get) => $get('destino_tipo') === 'url'),
            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->numeric()
                ->default(fn () => (SeccionMenu::max('orden') ?? 0) + 10)
                ->helperText('De menor a mayor: define el lugar en el menú.'),
            Forms\Components\Toggle::make('activo')
                ->label('Visible')
                ->default(true)
                ->helperText('Apagalo para sacarlo del menú sin borrarlo.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->columns([
                Tables\Columns\TextColumn::make('emoji')->label(''),
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('destino_tipo')
                    ->label('Lleva a')
                    ->formatStateUsing(fn (string $state) => SeccionMenu::DESTINOS[$state] ?? $state)
                    ->color('gray'),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Visible'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
                ]),
            ])
            ->emptyStateHeading('Tu menú está vacío')
            ->emptyStateDescription('Agregá las secciones que quieras que vean tus clientes.')
            ->defaultSort('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeccionesMenu::route('/'),
            'create' => Pages\CreateSeccionMenu::route('/create'),
            'edit' => Pages\EditSeccionMenu::route('/{record}/edit'),
        ];
    }
}
