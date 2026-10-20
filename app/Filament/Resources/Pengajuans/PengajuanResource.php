<?php

namespace App\Filament\Resources\Pengajuans;

use App\Enums\StatusPengajuan;
use App\Filament\Concerns\PunyaMenuTerfilter;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Models\Pengajuan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Pemantauan pengajuan TA/KP untuk Admin: HANYA BACA. Admin mengelola sistem dan
 * menjaga konsistensi data, bukan menyetujui atau menilai pengajuan, sehingga tidak
 * ada aksi ubah/setujui dan dokumen mahasiswa tidak dapat dibuka dari sini.
 */
class PengajuanResource extends Resource
{
    use PunyaMenuTerfilter;

    protected static ?string $model = Pengajuan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?string $modelLabel = 'pengajuan';

    protected static ?string $pluralModelLabel = 'pengajuan TA/KP';

    public static function getNavigationItems(): array
    {
        return [
            static::itemTerfilter('Daftar Pengajuan TA', 'Tugas Akhir (TA)', 'jenis', 'ta', 4),
            static::itemTerfilter('Daftar Pengajuan KP', 'Kerja Praktik (KP)', 'jenis', 'kp', 2),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis')->badge()->formatStateUsing(fn ($state) => $state->singkat()),
                TextColumn::make('mahasiswa.nama')->label('Mahasiswa')->searchable(),
                TextColumn::make('mahasiswa.nim')->label('NIM')->searchable(),
                TextColumn::make('prodi.kode')->label('Prodi'),
                TextColumn::make('judul')->label('Judul / Instansi')
                    ->state(fn (Pengajuan $record) => $record->judulTampil())
                    ->searchable(['judul', 'instansi_nama'])
                    ->limit(40),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('status_administrasi')->label('Administrasi')->formatStateUsing(fn ($state) => $state->label())->toggleable(),
                TextColumn::make('status_akademik')->label('Akademik')->formatStateUsing(fn ($state) => $state->label())->toggleable(),
                TextColumn::make('dospem.nama')->label('Dospem')->placeholder('-'),
                TextColumn::make('periode.nama')->label('Periode')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('diajukan_pada')->label('Diajukan')->dateTime('d M Y H:i')->placeholder('Draft')->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(['ta' => 'Tugas Akhir (TA)', 'kp' => 'Kerja Praktik (KP)']),
                SelectFilter::make('status')->options(StatusPengajuan::opsi()),
                SelectFilter::make('prodi_id')->label('Program studi')->relationship('prodi', 'nama'),
                SelectFilter::make('periode_akademik_id')->label('Periode')->relationship('periode', 'nama'),
            ])
            ->defaultSort('updated_at', 'desc');
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
            'index' => ListPengajuans::route('/'),
        ];
    }
}
