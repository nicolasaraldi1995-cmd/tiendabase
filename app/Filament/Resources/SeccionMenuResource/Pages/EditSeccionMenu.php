<?php

namespace App\Filament\Resources\SeccionMenuResource\Pages;

use App\Filament\Resources\SeccionMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeccionMenu extends EditRecord
{
    protected static string $resource = SeccionMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
