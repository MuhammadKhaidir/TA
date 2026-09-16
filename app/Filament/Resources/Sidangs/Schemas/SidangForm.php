<?php

namespace App\Filament\Resources\Sidangs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SidangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mahasiswa_id')
                    ->required()
                    ->numeric(),
                TextInput::make('jenis')
                    ->required(),
                DatePicker::make('tanggal_sidang'),
            ]);
    }
}
