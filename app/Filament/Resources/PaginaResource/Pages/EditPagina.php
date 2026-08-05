<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\PaginaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPagina extends EditRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = PaginaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
