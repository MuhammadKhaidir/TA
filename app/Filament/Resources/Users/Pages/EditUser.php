<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Peran;
use App\Filament\Resources\Users\UserResource;
use App\Models\LogAktivitas;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $peran = Peran::from($this->data['peran']);
        $this->record->syncRoles([$peran->value]);

        LogAktivitas::catat(auth()->user(), 'akun_diubah', "Akun {$this->record->email} diperbarui (peran {$peran->label()}, ".($this->record->aktif ? 'aktif' : 'nonaktif').').', $this->record);
    }
}
