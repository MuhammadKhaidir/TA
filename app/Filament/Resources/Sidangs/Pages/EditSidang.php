<?php

namespace App\Filament\Resources\Sidangs\Pages;

use App\Filament\Resources\Sidangs\SidangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSidang extends EditRecord
{
    protected static string $resource = SidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
