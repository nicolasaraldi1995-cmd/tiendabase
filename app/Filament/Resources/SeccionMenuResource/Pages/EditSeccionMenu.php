<?php

namespace App\Filament\Resources\SeccionMenuResource\Pages;

use App\Filament\Concerns\ExigeAccesoAlRecurso;
use App\Filament\Resources\SeccionMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeccionMenu extends EditRecord
{
    use ExigeAccesoAlRecurso;

    protected static string $resource = SeccionMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
