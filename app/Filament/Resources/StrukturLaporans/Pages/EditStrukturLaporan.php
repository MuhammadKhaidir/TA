<?php

namespace App\Filament\Resources\StrukturLaporans\Pages;

use App\Filament\Resources\StrukturLaporans\StrukturLaporanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStrukturLaporan extends EditRecord
{
    protected static string $resource = StrukturLaporanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
