<?php

namespace App\Filament\Resources\Mahasiswas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MahasiswaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nim')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->required(),
                TextInput::make('prodi')
                    ->required(),
                TextInput::make('angkatan')
                    ->required(),
                TextInput::make('no_hp')
                    ->tel(),
                TextInput::make('jumlah_konsultasi')
                    ->label('Jumlah Konsultasi')
                    ->helperText('Syarat minimal 12 kali sebelum sidang (Peringatan POS #1).')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
