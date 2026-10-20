<?php

namespace App\Filament\Resources\SuratKeputusans;

use App\Filament\Resources\SuratKeputusans\Pages\ListSuratKeputusans;
use App\Models\Prodi;
use App\Models\SuratKeputusan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Riwayat penerbitan SK TA (metadata saja, hanya baca). Berkas PDF hanya untuk pihak berwenang. */
class SuratKeputusanResource extends Resource
{
    protected static ?string $model = SuratKeputusan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Riwayat SK TA Terbit';

    protected static ?string $modelLabel = 'SK TA';

    protected static ?string $pluralModelLabel = 'SK TA terbit';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')->label('Nomor SK')->searchable()->sortable(),
                TextColumn::make('tanggal')->date('d M Y')->sortable(),
                TextColumn::make('pengajuan.mahasiswa.nama')->label('Mahasiswa')->searchable(),
                TextColumn::make('pengajuan.mahasiswa.nim')->label('NIM')->searchable(),
                TextColumn::make('pengajuan.prodi.kode')->label('Prodi'),
                TextColumn::make('penerbit.name')->label('Diterbitkan oleh'),
            ])
            ->filters([
                SelectFilter::make('prodi')->label('Program studi')
                    ->options(fn () => Prodi::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('pengajuan', fn ($q) => $q->where('prodi_id', $data['value']))
                        : $query),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratKeputusans::route('/'),
        ];
    }
}
