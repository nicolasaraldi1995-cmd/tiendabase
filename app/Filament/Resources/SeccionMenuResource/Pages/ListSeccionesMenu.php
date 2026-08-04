<?php

namespace App\Filament\Resources\SeccionMenuResource\Pages;

use App\Filament\Resources\SeccionMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeccionesMenu extends ListRecords
{
    protected static string $resource = SeccionMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nueva sección')];
    }
}
