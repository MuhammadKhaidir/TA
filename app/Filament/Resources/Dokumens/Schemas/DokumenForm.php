<?php

namespace App\Filament\Resources\Dokumens\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DokumenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sidang_id')
                    ->required()
                    ->numeric(),
                TextInput::make('jenis_dokumen')
                    ->required(),
                TextInput::make('nomor_sk'),
                TextInput::make('file_path')
                    ->required(),
                TextInput::make('uploaded_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
