<?php

namespace App\Filament\Resources\Pengaturans\Pages;

use App\Filament\Resources\Pengaturans\PengaturanResource;
use Filament\Resources\Pages\EditRecord;

class EditPengaturan extends EditRecord
{
    protected static string $resource = PengaturanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
