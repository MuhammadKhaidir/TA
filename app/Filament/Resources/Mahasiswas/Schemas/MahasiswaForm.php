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
                Select::make('prodi_id')
                    ->label('Program studi')
                    ->relationship('prodi', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('angkatan')
                    ->required(),
                TextInput::make('no_hp')
                    ->tel(),
            ]);
    }
}
