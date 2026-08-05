<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaginaResource\Pages;
use App\Models\Pagina;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaginaResource extends Resource
{
    protected static ?string $model = Pagina::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Páginas';

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'páginas';

    protected static ?int $navigationSort = 14;

    /**
     * Solo el admin: el contenido se publica tal cual en la tienda, así que no
     * es algo que deba tocar un operador.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titulo')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->helperText('Es el nombre que aparece en el pie de la tienda, ej: "Nosotros", "Cómo comprar", "Preguntas frecuentes".'),
            Forms\Components\RichEditor::make('contenido')
                ->label('Contenido')
                ->columnSpanFull()
                ->toolbarButtons([
                    'bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote', 'undo', 'redo',
                ]),
            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->numeric()
                ->default(0)
                ->helperText('De menor a mayor: define en qué orden se listan en el pie de la tienda.'),
            Forms\Components\Toggle::make('activo')
                ->label('Publicada')
                ->default(true)
                ->helperText('Si la apagás, la página deja de verse en la tienda pero no se borra.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Dirección')
                    ->prefix('/p/')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('orden')
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Publicada')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Publicada'),
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
            ->emptyStateHeading('Todavía no hay páginas')
            ->emptyStateDescription('Creá páginas como "Nosotros", "Cómo comprar" o "Preguntas frecuentes": aparecen solas en el pie de tu tienda.')
            ->defaultSort('orden');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaginas::route('/'),
            'create' => Pages\CreatePagina::route('/create'),
            'edit' => Pages\EditPagina::route('/{record}/edit'),
        ];
    }
}
