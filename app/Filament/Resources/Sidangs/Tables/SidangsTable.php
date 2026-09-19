<?php

namespace App\Filament\Resources\Sidangs\Tables;

use App\Enums\SidangStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SidangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mahasiswa.nama')
                    ->label('Mahasiswa')
                    ->searchable()
                    ->description(fn ($record) => $record->mahasiswa->nim),
                TextColumn::make('jenis')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->badge(),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->badge()
                    ->color(fn (SidangStatus $state): string => match (true) {
                        $state === SidangStatus::Selesai => 'success',
                        $state === SidangStatus::Ditunda => 'danger',
                        $state === SidangStatus::MenungguPenjadwalanUlang => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('tanggal_sidang')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('nomor_sk_penguji')
                    ->label('No. SK')
                    ->toggleable(),
                TextColumn::make('jumlah_penjadwalan_ulang')
                    ->label('Dijadwalkan Ulang')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SidangStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->recordActions([
                Action::make('lihatAlur')
                    ->label('Lihat Alur Proses')
                    ->url(fn ($record) => route('sidang.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
