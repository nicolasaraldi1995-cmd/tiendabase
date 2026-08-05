<?php

namespace App\Filament\Resources\SeccionMenuResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\SeccionMenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeccionMenu extends CreateRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = SeccionMenuResource::class;
}
