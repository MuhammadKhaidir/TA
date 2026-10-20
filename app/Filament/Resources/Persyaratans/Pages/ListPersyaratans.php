<?php

namespace App\Filament\Resources\Persyaratans\Pages;

use App\Filament\Resources\Persyaratans\PersyaratanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersyaratans extends ListRecords
{
    protected static string $resource = PersyaratanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
