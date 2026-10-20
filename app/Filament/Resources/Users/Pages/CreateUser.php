<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Peran;
use App\Filament\Resources\Users\UserResource;
use App\Models\LogAktivitas;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Hanya nilai dari enum Peran yang diterima; form tidak bisa menyisipkan role lain.
        $peran = Peran::from($this->data['peran']);
        $this->record->syncRoles([$peran->value]);

        LogAktivitas::catat(auth()->user(), 'akun_dibuat', "Akun {$this->record->email} dibuat dengan peran {$peran->label()}.", $this->record);
    }
}
