<?php

namespace App\Filament\Resources\Sidangs\Pages;

use App\Filament\Resources\Sidangs\SidangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSidangs extends ListRecords
{
    protected static string $resource = SidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
