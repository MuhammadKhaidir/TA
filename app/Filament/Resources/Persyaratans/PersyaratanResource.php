<?php

namespace App\Filament\Resources\Persyaratans;

use App\Enums\JenisPersyaratan;
use App\Filament\Concerns\PunyaMenuTerfilter;
use App\Filament\Resources\Persyaratans\Pages\CreatePersyaratan;
use App\Filament\Resources\Persyaratans\Pages\EditPersyaratan;
use App\Filament\Resources\Persyaratans\Pages\ListPersyaratans;
use App\Models\Persyaratan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PersyaratanResource extends Resource
{
    use PunyaMenuTerfilter;

    protected static ?string $model = Persyaratan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Tugas Akhir (TA)';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'persyaratan';

    protected static ?string $pluralModelLabel = 'persyaratan';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getNavigationItems(): array
    {
        return [
            static::itemTerfilter('Persyaratan Pengajuan TA', 'Tugas Akhir (TA)', 'jenis', 'ta_pengajuan', 1),
            static::itemTerfilter('Dokumen Surat Pengantar TA', 'Tugas Akhir (TA)', 'jenis', 'ta_surat_pengantar', 2),
            static::itemTerfilter('Dokumen Sidang TA', 'Tugas Akhir (TA)', 'jenis', 'ta_sidang', 7),
            static::itemTerfilter('Persyaratan Pengajuan KP', 'Kerja Praktik (KP)', 'jenis', 'kp_pengajuan', 1),
            static::itemTerfilter('Lampiran Laporan KP', 'Kerja Praktik (KP)', 'jenis', 'kp_lampiran', 4),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenis')->label('Untuk')->options(JenisPersyaratan::opsi())->required(),
            TextInput::make('nama')->required()->maxLength(255),
            Textarea::make('deskripsi')->rows(2)->columnSpanFull(),
            Select::make('tautan_otomatis')->label('Tautan otomatis (khusus dokumen sidang)')
                ->options(Persyaratan::opsiTautan())->placeholder('Diunggah mahasiswa')
                ->helperText('Bila dipilih, dokumen ditautkan otomatis oleh sistem dan tidak perlu diunggah mahasiswa.')
                ->visible(fn ($get) => $get('jenis') === JenisPersyaratan::TaSidang->value || $get('jenis') === JenisPersyaratan::TaSidang)
                ->live(),
            Toggle::make('wajib')->default(true),
            TextInput::make('waktu_penyerahan')->label('Waktu penyerahan (teks bebas)')->maxLength(255),
            TextInput::make('urutan')->numeric()->default(0),
            Select::make('prodi_id')->label('Berlaku untuk prodi')->relationship('prodi', 'nama')->placeholder('Semua program studi')->preload(),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('nama')->searchable(),
                IconColumn::make('wajib')->boolean(),
                TextColumn::make('tautan_otomatis')->label('Tautan')->placeholder('-')
                    ->formatStateUsing(fn ($state) => Persyaratan::opsiTautan()[$state] ?? $state)->toggleable(),
                TextColumn::make('prodi.kode')->label('Prodi')->placeholder('Semua'),
                TextColumn::make('urutan')->sortable(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(JenisPersyaratan::opsi()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('urutan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersyaratans::route('/'),
            'create' => CreatePersyaratan::route('/create'),
            'edit' => EditPersyaratan::route('/{record}/edit'),
        ];
    }
}
