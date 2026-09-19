<?php

namespace App\Filament\Resources\Dokumens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DokumensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sidang.mahasiswa.nama')
                    ->label('Mahasiswa')
                    ->searchable(),
                TextColumn::make('jenis_dokumen')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->badge()
                    ->searchable(),
                TextColumn::make('nomor_sk')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nama_file_asli')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('uploader.email')
                    ->label('Diunggah oleh')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
