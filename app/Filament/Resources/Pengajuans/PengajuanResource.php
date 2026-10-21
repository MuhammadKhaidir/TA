<?php

namespace App\Filament\Resources\Pengajuans;

use App\Enums\StatusPengajuan;
use App\Filament\Concerns\JalankanAksi;
use App\Filament\Concerns\PunyaMenuTerfilter;
use App\Filament\Resources\Pengajuans\Pages\ListPengajuans;
use App\Models\Pengaturan;
use App\Models\Pengajuan;
use App\Services\SkService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Pemantauan pengajuan TA/KP untuk Admin. Admin mengelola sistem dan menjaga konsistensi data,
 * bukan menyetujui atau menilai pengajuan, sehingga tidak ada aksi ubah/setujui dan dokumen
 * mahasiswa tidak dapat dibuka dari sini.
 *
 * Satu-satunya aksi: "Catat SK offline" (revisi PDF Bagian 3), untuk pelaku tugas pencatatan SK
 * (bawaan Admin) ketika jalur SK diatur offline: nomor, tanggal, dan salinan SK resmi dicatat dan
 * pengajuan menjadi "SK terbit". Syaratnya sama dengan penerbitan dari website, termasuk surat balasan valid.
 */
class PengajuanResource extends Resource
{
    use JalankanAksi;
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
                TextColumn::make('dospem.nama')->label('Pembimbing 1')->placeholder('-'),
                TextColumn::make('dospem2.nama')->label('Pembimbing 2')->placeholder('-')->toggleable(),
                TextColumn::make('periode.nama')->label('Periode')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('diajukan_pada')->label('Diajukan')->dateTime('d M Y H:i')->placeholder('Draft')->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis')->options(['ta' => 'Tugas Akhir (TA)', 'kp' => 'Kerja Praktik (KP)']),
                SelectFilter::make('status')->options(StatusPengajuan::opsi()),
                SelectFilter::make('prodi_id')->label('Program studi')->relationship('prodi', 'nama'),
                SelectFilter::make('periode_akademik_id')->label('Periode')->relationship('periode', 'nama'),
                Filter::make('siap_catat_sk')->label('TA siap dicatat SK')
                    ->query(fn ($query) => $query->where('jenis', 'ta')->where('status', StatusPengajuan::Disetujui->value)->whereDoesntHave('suratKeputusan')),
            ])
            ->recordActions([
                Action::make('catatSk')
                    ->label('Catat SK offline')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->color('primary')
                    ->visible(fn (Pengajuan $record) => Pengaturan::skMode() === Pengaturan::SK_OFFLINE
                        && $record->siapTerbitSk()
                        && Gate::allows('catatSk', $record))
                    ->modalHeading('Catat SK resmi (jalur offline)')
                    ->modalDescription('SK resmi diterbitkan di luar sistem. Catat nomor, tanggal, dan unggah salinannya.')
                    ->schema([
                        TextInput::make('nomor')->label('Nomor SK')->required()->maxLength(255),
                        DatePicker::make('tanggal')->label('Tanggal SK')->required()->maxDate(now()),
                        FileUpload::make('salinan')->label('Salinan SK (PDF atau gambar)')
                            ->storeFiles(false)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->required(),
                    ])
                    ->action(function (Pengajuan $record, array $data) {
                        $berkas = is_array($data['salinan']) ? reset($data['salinan']) : $data['salinan'];

                        static::jalankanAksi(function () use ($record, $data, $berkas) {
                            if (! $berkas instanceof UploadedFile) {
                                throw new \App\Exceptions\WorkflowException('Salinan SK wajib diunggah.');
                            }
                            app(SkService::class)->catatOffline($record, Auth::user(), $data['nomor'], $data['tanggal'], $berkas);
                        }, 'SK resmi dicatat. Status pengajuan menjadi SK terbit.');
                    }),
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
