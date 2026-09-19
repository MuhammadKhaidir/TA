<?php

namespace App\Filament\Resources\Dokumens\Schemas;

use App\Enums\JenisDokumen;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DokumenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sidang_id')
                    ->relationship('sidang', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->mahasiswa->nama.' — '.$record->jenis->label())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('jenis_dokumen')
                    ->options(collect(JenisDokumen::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->required(),
                TextInput::make('nomor_sk')
                    ->label('Nomor SK (jika relevan)'),
                TextInput::make('file_path')
                    ->required()
                    ->helperText('Path relatif pada disk "public", contoh: sidang/1/berkas.pdf'),
                TextInput::make('nama_file_asli')
                    ->label('Nama File Asli'),
                Textarea::make('keterangan')
                    ->rows(2),
                Select::make('uploaded_by')
                    ->relationship('uploader', 'email')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
