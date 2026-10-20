<?php

namespace App\Filament\Resources\TemplateSks\Pages;

use App\Filament\Resources\TemplateSks\TemplateSkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplateSks extends ListRecords
{
    protected static string $resource = TemplateSkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
