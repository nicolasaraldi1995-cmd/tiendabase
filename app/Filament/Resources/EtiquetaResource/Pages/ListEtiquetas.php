<?php

namespace App\Filament\Resources\EtiquetaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\EtiquetaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEtiquetas extends ListRecords
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = EtiquetaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
