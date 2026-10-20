<?php

namespace App\Filament\Resources\Persyaratans\Pages;

use App\Filament\Resources\Persyaratans\PersyaratanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPersyaratan extends EditRecord
{
    protected static string $resource = PersyaratanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
