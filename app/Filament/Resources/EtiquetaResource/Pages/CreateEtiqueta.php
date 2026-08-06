<?php

namespace App\Filament\Resources\EtiquetaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\EtiquetaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEtiqueta extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = EtiquetaResource::class;
}
