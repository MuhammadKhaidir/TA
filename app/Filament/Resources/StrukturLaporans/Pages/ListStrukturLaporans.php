<?php

namespace App\Filament\Resources\StrukturLaporans\Pages;

use App\Filament\Resources\StrukturLaporans\StrukturLaporanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStrukturLaporans extends ListRecords
{
    protected static string $resource = StrukturLaporanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
