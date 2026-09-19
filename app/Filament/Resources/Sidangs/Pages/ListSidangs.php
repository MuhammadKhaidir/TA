<?php

namespace App\Filament\Resources\Sidangs\Pages;

use App\Filament\Resources\Sidangs\SidangResource;
use Filament\Resources\Pages\ListRecords;

class ListSidangs extends ListRecords
{
    protected static string $resource = SidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sidang hanya dapat dibuat melalui alur pengajuan mahasiswa
            // (lihat SidangWorkflowService), sehingga tidak disediakan
            // aksi "Buat" manual di panel admin.
        ];
    }
}
