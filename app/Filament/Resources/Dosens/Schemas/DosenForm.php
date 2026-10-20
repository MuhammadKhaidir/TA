<?php

namespace App\Filament\Resources\Dosens\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DosenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Akun pengguna (wajib berperan Dospem agar dapat membimbing)')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                TextInput::make('nip')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->required(),
                Select::make('prodi_id')
                    ->label('Program studi')
                    ->relationship('prodi', 'nama')
                    ->searchable()
                    ->preload(),
                Toggle::make('aktif')
                    ->label('Aktif (dapat ditetapkan sebagai pembimbing)')
                    ->default(true),
            ]);
    }
}
