<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\PaginaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePagina extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = PaginaResource::class;
}
