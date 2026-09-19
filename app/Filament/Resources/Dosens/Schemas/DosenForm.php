<?php

namespace App\Filament\Resources\Dosens\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DosenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Akun Pengguna (opsional)')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                TextInput::make('nip')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->required(),
                TextInput::make('prodi'),
            ]);
    }
}
