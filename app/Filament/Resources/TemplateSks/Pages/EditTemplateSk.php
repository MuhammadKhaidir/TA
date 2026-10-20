<?php

namespace App\Filament\Resources\TemplateSks\Pages;

use App\Filament\Resources\TemplateSks\TemplateSkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplateSk extends EditRecord
{
    protected static string $resource = TemplateSkResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
